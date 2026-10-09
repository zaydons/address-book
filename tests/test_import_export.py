"""Importing contacts from vCard and CSV files, and exporting them."""
import html
import os
import re

from conftest import add_contact, sql, token, unique, url

FIXTURES = os.path.join(os.path.dirname(__file__), "fixtures")


def upload(session, name, content=None, skip_duplicates=True):
    """Import a file, returning the summary shown on the page as plain text."""
    if content is None:
        content = open(os.path.join(FIXTURES, name), "rb").read()
    form = {"csrf_token": token(session.get(url("import.php")).text), "submit": "submit"}
    if skip_duplicates:
        form["skip_duplicates"] = "1"
    page = session.post(url("import.php"), data=form, files={"file": (name, content)}).text
    match = re.search(r'<div class="alert[^"]*" role="alert">(.*?)</div>', page, re.S)
    return re.sub(r"\s+", " ", html.unescape(re.sub("<[^>]+>", " ", match.group(1)))).strip() if match else ""


def contact(first_name):
    rows = sql("SELECT first_name, middle_name, last_name, contact_number_mobile, contact_number_home, contact_email, date_of_birth, "
               "address_line_1, address_line_2, address_town, address_county, address_post_code FROM contacts WHERE first_name = ? LIMIT 1", first_name)
    return rows[0] if rows else None


def test_android_vcard_21_with_accents_photo_and_fax(admin):
    summary = upload(admin, "android.vcf", skip_duplicates=False)
    assert summary.startswith("2 contacts added")
    assert contact("Zoë") == ["Zoë", None, "Brontë", "+447700900123", "01535642323", "zoe@example.com", None,
                               "Haworth Parsonage", "Church Street", "Keighley", "West Yorkshire", "BD22 8DR"]
    assert contact("Jo")[:4] == ["Jo", None, "Smith", "2125550101"]


def test_iphone_vcard_30_with_grouped_numbers_and_a_card_without_a_name(admin):
    summary = upload(admin, "iphone.vcf", skip_duplicates=False)
    assert summary.startswith("2 contacts added") and "Contact 3: First name is a required field" in summary
    assert contact("Jane") == ["Jane", None, "Austen", "+12125550102", "7185550103", "jane@example.com", "1975-12-16",
                                "Winchester Road", "Chawton Cottage", "Alton", "Hampshire", "GU34 1SD"]
    # A card with only a formatted name is split into first and last name
    assert contact("Plumber")[:4] == ["Plumber", None, "Bob", "5550199"]


def test_vcard_40(admin):
    assert upload(admin, "v4.vcf", skip_duplicates=False).startswith("1 contact added")
    assert contact("Charles")[:4] + [contact("Charles")[6]] == ["Charles", "John", "Dickens", "+12125550104", "1972-02-07"]


def test_csv_with_other_column_names_and_problem_rows(admin):
    summary = upload(admin, "other.csv", skip_duplicates=False)
    assert summary.startswith("2 contacts added")
    assert "Row 4 (Bad Date): The date of birth is not a valid date." in summary
    assert "Row 6 (Only Phone): The mobile contact number can only contain" in summary
    assert contact("Mary") == ["Mary", None, "Shelley", "+12125550105", None, "mary@example.com", "1997-08-30", None, None, "London", None, "SW1W 9HS"]


def test_files_which_cannot_be_imported(admin):
    page = admin.post(url("import.php"), data={"csrf_token": token(admin.get(url("import.php")).text), "submit": "submit"}).text
    assert "Please choose a CSV or vCard" in page
    assert "needs a header row" in upload(admin, "bad.csv", b"Phone,Email\n123,a@b.c\n")


def test_import_skips_contacts_already_in_the_address_book(admin):
    upload(admin, "android.vcf", skip_duplicates=False)
    assert upload(admin, "android.vcf") == "0 contacts added. 2 skipped as already in the address book."


def test_export_and_reimport_round_trip(admin):
    name = unique("Export")
    add_contact(admin, first_name=name, last_name="Ñoño", contact_number_mobile="+1 (212) 555-0199", contact_email="x@example.com",
                date_of_birth="2000-01-31", address_line_1="1 Main St", address_line_2="Apt 2", address_town="Springfield",
                address_county="IL", address_post_code="62701")

    csv = admin.get(url("export.php?format=csv"))
    assert csv.headers["Content-Type"].startswith("text/csv") and "attachment" in csv.headers["Content-Disposition"]
    assert csv.content.startswith(b"\xef\xbb\xbf")
    text = csv.content.decode("utf-8-sig")
    assert text.splitlines()[0].startswith('"First Name","Middle Name","Last Name"')
    # A value starting with + is written with a ' so that spreadsheets don't treat it as a formula
    assert "'+1 (212) 555-0199" in text

    vcf = admin.get(url("export.php?format=vcf"))
    assert vcf.headers["Content-Type"].startswith("text/vcard")
    assert "N:Ñoño;" + name + ";;;" in vcf.text and "ADR;TYPE=HOME:;;1 Main St\\nApt 2;Springfield;IL;62701;" in vcf.text
    assert max(len(line.encode()) for line in vcf.text.split("\r\n")) <= 75

    # Importing either file again finds every contact already there, so nothing is changed or lost on the way
    total = len(sql("SELECT 1 FROM contacts"))
    distinct = len(sql("SELECT DISTINCT lower(first_name), lower(coalesce(last_name, '')), coalesce(contact_number_mobile, ''), "
                       "coalesce(contact_number_home, ''), lower(coalesce(contact_email, '')) FROM contacts"))
    assert upload(admin, "export.csv", csv.content) == "0 contacts added. %d skipped as already in the address book." % total
    assert upload(admin, "export.vcf", vcf.content) == "0 contacts added. %d skipped as already in the address book." % total
    assert len(sql("SELECT 1 FROM contacts")) == total and distinct <= total
