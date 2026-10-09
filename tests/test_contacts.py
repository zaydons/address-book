"""Adding, viewing, updating and deleting contacts, phone numbers and the API."""
import html
import re

import requests

from conftest import add_contact, contact_id, no_php_errors, sql, sql_value, token, unique, url

FIELDS = ["first_name", "middle_name", "last_name", "contact_number_home", "contact_number_mobile", "contact_email", "date_of_birth",
          "address_line_1", "address_line_2", "address_town", "address_county", "address_post_code"]


def update_contact(session, cid, **changes):
    page = session.get(url("update-contact.php?i=" + cid))
    current = {field: html.unescape(value) for field, value in
               re.findall(r'name="(\w+)"[^>]*?value="([^"]*)"', page.text) if field in FIELDS}
    data = {field: current.get(field, "") for field in FIELDS}
    data.update(changes)
    data.update({"csrf_token": token(page.text), "submit": "submit"})
    return session.post(url("update-contact.php?i=" + cid), data=data)


def test_only_a_first_name_is_needed(admin):
    name = unique("Mom")
    response = add_contact(admin, first_name=name)
    assert "successfully added" in response.text
    view = admin.get(url("view-contact.php?i=" + contact_id(name)))
    assert name in view.text and "Address:" not in view.text and no_php_errors(view.text)


def test_phone_numbers_can_be_typed_in_any_format_and_are_shown_in_us_format(admin):
    name = unique("Phone")
    response = add_contact(admin, first_name=name, contact_number_mobile="+1 (212) 555-1234", contact_number_home="718.555.0000")
    assert "successfully added" in response.text
    assert sql("SELECT contact_number_mobile, contact_number_home FROM contacts WHERE first_name = ?", name)[0] == ["+12125551234", "7185550000"]
    assert "+1 (212) 555-1234" in response.text
    view = admin.get(url("view-contact.php?i=" + contact_id(name))).text
    assert "(718) 555-0000" in view and 'href="tel:+12125551234"' in view


def test_invalid_values_are_rejected(admin):
    name = unique("Bad")
    response = add_contact(admin, first_name=name, contact_number_mobile="call me", contact_email="not-an-email", date_of_birth="2024-02-30")
    assert "mobile contact number can only contain" in response.text
    assert "email address is not valid" in response.text
    assert "not a valid date" in response.text
    assert contact_id(name) is None
    assert "First name is a required field" in add_contact(admin, first_name="").text


def test_updating_a_contact_saves_every_field_and_can_clear_them(admin):
    name = unique("Update")
    add_contact(admin, first_name=name, middle_name="Middle", last_name="Last", contact_number_home="2125550001",
                contact_email="old@example.com", date_of_birth="1980-01-02", address_line_2="Flat 1")
    cid = contact_id(name)
    response = update_contact(admin, cid, middle_name="", contact_number_home="", contact_email="", date_of_birth="", address_line_2="",
                              contact_number_mobile="(646) 555-0002")
    assert "successfully updated" in response.text
    row = sql("SELECT first_name, middle_name, last_name, contact_number_home, contact_number_mobile, contact_email, date_of_birth, address_line_2 "
              "FROM contacts WHERE contact_id = ?", cid)[0]
    assert row == [name, None, "Last", None, "6465550002", None, None, None]


def test_clearing_the_first_name_is_refused_and_keeps_the_middle_name(admin):
    name = unique("First")
    add_contact(admin, first_name=name, middle_name="Keep")
    cid = contact_id(name)
    response = update_contact(admin, cid, first_name="")
    assert "First name is a required field" in response.text
    assert sql("SELECT first_name, middle_name FROM contacts WHERE contact_id = ?", cid)[0] == [name, "Keep"]
    # After a refused update, the form shows what was typed (the cleared first name), not the saved value
    first_name_input = re.search(r'<input[^>]*name="first_name"[^>]*>', response.text).group(0)
    assert name not in first_name_input


def test_delete_contact_needs_confirmation(admin):
    name = unique("Delete")
    add_contact(admin, first_name=name)
    cid = contact_id(name)
    page = admin.get(url("delete-contact.php?i=" + cid))
    admin.post(url("delete-contact.php?i=" + cid), data={"csrf_token": token(page.text), "submit": "submit"})
    assert contact_id(name) == cid
    admin.post(url("delete-contact.php?i=" + cid), data={"csrf_token": token(page.text), "submit": "submit", "confirm_delete": "on"})
    assert contact_id(name) is None


def test_contact_pages_have_no_php_errors_for_contacts_without_details(admin):
    name = unique("Bare")
    add_contact(admin, first_name=name)
    cid = contact_id(name)
    for page in ["view-contact.php?i=", "update-contact.php?i=", "delete-contact.php?i="]:
        response = admin.get(url(page + cid))
        assert response.status_code == 200 and no_php_errors(response.text), page
    assert no_php_errors(admin.get(url("view-contact.php")).text)


def api_token(admin):
    page = admin.get(url("add-api.php"))
    admin.post(url("add-api.php"), data={"cosmetic_name": unique("api"), "ip": "", "csrf_token": token(page.text), "submit": "submit"})
    return sql_value("SELECT api_id FROM api ORDER BY rowid DESC LIMIT 1")


def test_api_finds_numbers_in_any_format(admin):
    name = unique("Caller")
    add_contact(admin, first_name=name, last_name="Id", contact_number_mobile="(917) 555-0142")
    key = api_token(admin)
    headers = {"Authorization": "Bearer " + key}
    for query in ["9175550142", "+19175550142", "1-917-555-0142", "(917) 555-0142"]:
        response = requests.get(url("api.php"), params={"m": "findNumber", "q": query}, headers=headers)
        assert response.status_code == 200 and response.json()["result"] == name + " Id", query
    assert requests.get(url("api.php"), params={"m": "findNumber", "q": "5550142"}, headers=headers).status_code == 404


def test_api_tokens_errors_and_logging(admin):
    key = api_token(admin)
    assert len(key) == 12
    # The old ?t= way still works, and API calls don't create sessions
    response = requests.get(url("api.php"), params={"t": key, "m": "findNumber", "q": "0000000000"})
    assert response.status_code == 404 and "Set-Cookie" not in response.headers
    response = requests.get(url("api.php"), params={"m": "findNumber", "q": "1"}, headers={"Authorization": "Bearer WRONGTOKEN12"})
    assert response.status_code == 401 and "Set-Cookie" not in response.headers
    assert requests.get(url("api.php"), params={"t": key}).status_code == 400
    assert requests.get(url("api.php") + "?t=" + key + "&m[]=x&q[]=y").status_code == 400
    # Only the start of a token is recorded in the logs
    logs = " ".join(row[0] + " " + row[1] for row in sql("SELECT action, url FROM logs WHERE action LIKE '%Token%' OR url LIKE '%api.php%'"))
    assert key not in logs and key[:4] + "..." in logs
