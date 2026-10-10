#!/usr/bin/env python3
"""Imports contacts from an Excel (.xlsx) spreadsheet into the address book, such as one exported from Numbers.

The spreadsheet needs a header row. These columns are used, in any order (other columns are ignored):
  Name                  the name to show, such as "Michael & Catherine Smith" or "Mr & Mrs John Smith"
  Last Name             (optional) the surname. If Name ends with it, Name is split into first and last names
  Address, Address 2, City, State, Zip
  Phone, Mobile, Email  (optional) and any other column which the Import page accepts, such as "Date of Birth"

Converts the spreadsheet into a CSV file next to it, which can be checked and then imported from the Import page:
  python3 tools/import-spreadsheet.py Address_Book.xlsx

Or also imports it straight away, logging in with your username (it asks for the password):
  python3 tools/import-spreadsheet.py Address_Book.xlsx --url http://192.168.0.31:8080/ --username admin

Contacts which are already in the address book (the same name, phone numbers and email address) are skipped, so
running it again doesn't add them twice. Needs only Python 3.
"""
import argparse
import csv
import getpass
import html
import http.cookiejar
import io
import os
import re
import sys
import urllib.parse
import urllib.request
import uuid
import xml.etree.ElementTree as ET
import zipfile

NS = {
    'main': 'http://schemas.openxmlformats.org/spreadsheetml/2006/main',
    'rel': 'http://schemas.openxmlformats.org/officeDocument/2006/relationships',
    'pkg': 'http://schemas.openxmlformats.org/package/2006/relationships',
}

# The longest first name the address book accepts
FIRST_NAME_LENGTH = 50


def read_xlsx(path):
    """Every worksheet in the file, as lists of rows of text."""
    with zipfile.ZipFile(path) as book:
        shared = []
        if 'xl/sharedStrings.xml' in book.namelist():
            for item in ET.fromstring(book.read('xl/sharedStrings.xml')).findall('main:si', NS):
                shared.append(''.join(text.text or '' for text in item.iter('{%s}t' % NS['main'])))

        targets = {rel.get('Id'): rel.get('Target') for rel in ET.fromstring(book.read('xl/_rels/workbook.xml.rels')).findall('pkg:Relationship', NS)}
        sheets = []
        for sheet in ET.fromstring(book.read('xl/workbook.xml')).find('main:sheets', NS):
            target = targets[sheet.get('{%s}id' % NS['rel'])].lstrip('/')
            target = target if target.startswith('xl/') else 'xl/' + target
            rows = []
            for row in ET.fromstring(book.read(target)).iter('{%s}row' % NS['main']):
                values = {}
                for cell in row.findall('main:c', NS):
                    column = column_number(cell.get('r')) if cell.get('r') else len(values)
                    values[column] = cell_text(cell, shared)
                rows.append([values.get(i, '') for i in range(max(values) + 1)] if values else [])
            sheets.append((sheet.get('name'), rows))
        return sheets


def column_number(reference):
    """0 for A1, 1 for B1, and so on."""
    number = 0
    for letter in re.match(r'[A-Z]+', reference).group():
        number = number * 26 + ord(letter) - ord('A') + 1
    return number - 1


def cell_text(cell, shared):
    kind = cell.get('t')
    if kind == 'inlineStr':
        return ''.join(text.text or '' for text in cell.iter('{%s}t' % NS['main'])).strip()
    value = cell.find('main:v', NS)
    if value is None or value.text is None:
        return ''
    if kind == 's':
        return shared[int(value.text)].strip()
    if kind in (None, 'n'):
        # Numbers, such as a zip code typed as a number: show whole numbers without ".0"
        number = float(value.text)
        return str(int(number)) if number.is_integer() else value.text
    return value.text.strip()


def find_table(sheets):
    """The first worksheet with a header row containing a Name or First Name column, and its header row's position."""
    for name, rows in sheets:
        for index, row in enumerate(rows[:10]):
            headings = [heading.strip().lower() for heading in row]
            if 'name' in headings or 'first name' in headings:
                return name, index, rows
    sys.exit('No worksheet has a header row with a "Name" or "First Name" column.')


def contacts_from(rows, header_index):
    """The contacts as dictionaries of the address book's CSV columns, and a list of notes about them."""
    headings = [heading.strip() for heading in rows[header_index]]
    lower = [heading.lower() for heading in headings]
    contacts, notes = [], []
    for number, row in enumerate(rows[header_index + 1:], start=header_index + 2):
        values = {lower[i]: row[i].strip() for i in range(min(len(row), len(lower))) if lower[i]}
        if not any(values.values()):
            continue

        contact = {}
        if 'name' in values:
            # Split "Michael & Catherine Smith" into "Michael & Catherine" and "Smith" using the Last Name column,
            # so that the name shows as it is in the spreadsheet. Names which don't end with the last name (such as
            # "Sean Brain & Jandy Hanna") are kept whole as the first name.
            name, last = values.pop('name'), values.pop('last name', '')
            if last and name.lower().endswith(' ' + last.lower()):
                contact['First Name'], contact['Last Name'] = name[:-len(last)].strip(), name[-len(last):]
            else:
                contact['First Name'], contact['Last Name'] = name, ''
                if last:
                    notes.append('Row %d: "%s" doesn\'t end with the last name "%s", so it is kept whole as the first name' % (number, name, last))
            if len(contact['First Name']) > FIRST_NAME_LENGTH:
                notes.append('Row %d: "%s" is longer than %d characters, so the address book won\'t add it' % (number, contact['First Name'], FIRST_NAME_LENGTH))

        # Every other column goes to the address book as it is, using the spreadsheet's heading
        for heading in headings:
            if heading.lower() in values:
                contact[heading] = values[heading.lower()]
        contacts.append(contact)
    return contacts, notes


def write_csv(contacts, path):
    columns = []
    for contact in contacts:
        columns += [column for column in contact if column not in columns]
    with open(path, 'w', newline='', encoding='utf-8') as file:
        writer = csv.DictWriter(file, fieldnames=columns)
        writer.writeheader()
        writer.writerows(contacts)


def upload(csv_path, url, username, password):
    """Logs in to the address book and imports the CSV file using the Import page, then prints the result."""
    base = url.rstrip('/') + '/'
    opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def get(page):
        with opener.open(base + page) as response:
            return response.geturl(), response.read().decode('utf-8', 'replace')

    def token(page_html):
        found = re.search(r'name="csrf_token" value="([^"]+)"', page_html)
        if not found:
            sys.exit('Could not read the page from %s. Is the address right?' % base)
        return html.unescape(found.group(1))

    _, page = get('login.php')
    form = urllib.parse.urlencode({'username': username, 'password': password, 'csrf_token': token(page), 'submit': 'submit'}).encode()
    with opener.open(base + 'login.php', form) as response:
        landed = response.geturl()
    if 'change-password.php' in landed:
        sys.exit('Log in to the address book in a browser and choose a new password first, then run this again.')
    if 'login.php' in landed:
        sys.exit('Could not log in. Check the username and password.')

    _, page = get('import.php')
    boundary = uuid.uuid4().hex
    body = io.BytesIO()
    for name, value in (('csrf_token', token(page)), ('skip_duplicates', '1'), ('submit', 'submit')):
        body.write(('--%s\r\nContent-Disposition: form-data; name="%s"\r\n\r\n%s\r\n' % (boundary, name, value)).encode())
    body.write(('--%s\r\nContent-Disposition: form-data; name="file"; filename="%s"\r\nContent-Type: text/csv\r\n\r\n' % (boundary, os.path.basename(csv_path))).encode())
    with open(csv_path, 'rb') as file:
        body.write(file.read())
    body.write(('\r\n--%s--\r\n' % boundary).encode())
    request = urllib.request.Request(base + 'import.php', body.getvalue(), {'Content-Type': 'multipart/form-data; boundary=' + boundary})
    with opener.open(request) as response:
        page = response.read().decode('utf-8', 'replace')

    # The result is shown in an alert box at the top of the page
    alert = re.search(r'<div class="alert[^"]*"[^>]*>(.*?)</div>', page, re.S)
    if not alert:
        sys.exit('The import page didn\'t show a result.')
    text = re.sub(r'<li>', '\n  - ', alert.group(1))
    text = html.unescape(re.sub(r'<[^>]+>', '', text))
    print('\n'.join(line.strip() if not line.strip().startswith('-') else '  ' + line.strip() for line in text.splitlines() if line.strip()))

    try:
        opener.open(urllib.request.Request(base + 'logout.php', urllib.parse.urlencode({'csrf_token': token(page)}).encode()))
    except Exception:
        pass


def main():
    parser = argparse.ArgumentParser(description='Import contacts from an Excel (.xlsx) spreadsheet into the address book.')
    parser.add_argument('spreadsheet', help='the .xlsx file')
    parser.add_argument('--csv', help='where to write the CSV file (default: next to the spreadsheet)')
    parser.add_argument('--url', help='the address book\'s address, such as http://192.168.0.31:8080/, to import the contacts straight away')
    parser.add_argument('--username', help='the username to log in with (default: admin)', default='admin')
    args = parser.parse_args()

    sheet, header_index, rows = find_table(read_xlsx(args.spreadsheet))
    contacts, notes = contacts_from(rows, header_index)
    csv_path = args.csv or os.path.splitext(args.spreadsheet)[0] + '.csv'
    write_csv(contacts, csv_path)

    print('Read %d contacts from "%s".' % (len(contacts), sheet))
    for contact in contacts[:5]:
        print('  %-35s first name: %-28s last name: %s' % ((contact.get('First Name', '') + ' ' + contact.get('Last Name', '')).strip(), contact.get('First Name', ''), contact.get('Last Name', '')))
    if len(contacts) > 5:
        print('  ...')
    for note in notes:
        print(note)
    print('Saved them in %s.' % csv_path)

    if args.url:
        password = getpass.getpass('Password for %s: ' % args.username)
        upload(csv_path, args.url, args.username, password)
    else:
        print('Check it, then import it from the Import page, or run this again with --url to import it.')


if __name__ == '__main__':
    main()
