"""Logging in and out, passwords, sessions, CSRF protection and other security measures."""
import requests

from conftest import (ADMIN_PASSWORD, DEFAULT_ADMIN_PASSWORD, login, no_php_errors, reset_failed_logins, sql, sql_value, token, unique, url)


def test_default_admin_must_change_password_at_first_login(first_admin_login):
    assert first_admin_login.url.endswith("change-password.php") or sql_value("SELECT must_change_password FROM users WHERE username = 'admin'") == 0
    assert sql_value("SELECT must_change_password FROM users WHERE username = 'admin'") == 0
    assert sql_value("SELECT hashed_password FROM users WHERE username = 'admin'").startswith("$2y$")


def test_old_default_password_no_longer_works(admin):
    reset_failed_logins()
    response = login(requests.Session(), "admin", DEFAULT_ADMIN_PASSWORD)
    assert "combination not found" in response.text


def test_usernames_are_not_case_sensitive(admin):
    response = login(requests.Session(), "ADMIN", ADMIN_PASSWORD)
    assert response.url.endswith("index.php")


def test_pages_need_login():
    for page in ["index.php", "users.php", "logs.php", "api.php", "add-contact.php", "import.php", "export.php"]:
        assert requests.get(url(page)).url.endswith("login.php"), page


def test_security_headers():
    response = requests.get(url("login.php"))
    assert response.headers["X-Frame-Options"] == "DENY"
    assert "frame-ancestors 'none'" in response.headers["Content-Security-Policy"]
    assert response.headers["X-Content-Type-Options"] == "nosniff"
    assert response.headers["Referrer-Policy"] == "same-origin"
    assert "X-Powered-By" not in response.headers
    assert response.headers.get("Server") in (None, "Apache")


def test_session_cookie_is_hardened_and_long_lived():
    cookie = requests.get(url("login.php")).headers["Set-Cookie"]
    assert "HttpOnly" in cookie and "SameSite=Lax" in cookie
    # Logins last SESSION_LIFETIME_DAYS (365 by default), rather than ending when the browser is closed
    assert "expires=" in cookie.lower() and "max-age=" in cookie.lower()


def test_session_id_changes_on_login_and_unknown_session_ids_are_rejected(admin):
    session = requests.Session()
    session.get(url("login.php"))
    before = session.cookies.get("PHPSESSID")
    login(session, "admin", ADMIN_PASSWORD)
    assert session.cookies.get("PHPSESSID") != before

    attacker = requests.Session()
    attacker.cookies.set("PHPSESSID", "chosenbyattacker1234567890", domain=url().split("/")[2].split(":")[0])
    assert "chosenbyattacker" not in attacker.get(url("login.php")).headers.get("Set-Cookie", "")


def test_sessions_are_saved_next_to_the_database(admin):
    import subprocess
    from conftest import APP_CONTAINER
    files = subprocess.run(["docker", "exec", APP_CONTAINER, "ls", "/data/sessions"], capture_output=True, text=True).stdout.split()
    assert any(name.startswith("sess_") for name in files)


def test_logout_needs_a_post_with_csrf_token(admin):
    page = admin.get(url("logout.php"))
    assert "Are you sure" in page.text
    assert admin.get(url("index.php")).url.endswith("index.php")
    admin.post(url("logout.php"), data={"csrf_token": "wrong"})
    assert admin.get(url("index.php")).url.endswith("index.php")
    before = admin.cookies.get("PHPSESSID")
    response = admin.post(url("logout.php"), data={"csrf_token": token(admin.get(url("index.php")).text)})
    assert response.url.endswith("login.php") and "logged out successfully" in response.text
    assert admin.cookies.get("PHPSESSID") != before
    assert admin.get(url("index.php")).url.endswith("login.php")


def test_login_page_escapes_the_username():
    reset_failed_logins()
    response = login(requests.Session(), '"><script>alert(1)</script>', "x")
    assert "<script>alert(1)</script>" not in response.text
    reset_failed_logins()


def test_forms_reject_missing_csrf_token(admin):
    name = unique("Csrf")
    admin.post(url("add-contact.php"), data={"first_name": name, "submit": "submit"})
    assert sql_value("SELECT COUNT(*) FROM contacts WHERE first_name = ?", name) == 0


def test_new_user_and_password_reset_must_change_password(admin):
    username = unique("user")
    page = admin.get(url("add-user.php"))
    response = admin.post(url("add-user.php"), data={"full_name": "Test User", "username": username, "password": "Temp1234x",
                                                   "confirm_password": "Temp1234x", "csrf_token": token(page.text), "submit": "submit"})
    assert "successfully added" in response.text
    user_id, must_change = sql("SELECT user_id, must_change_password FROM users WHERE username = ?", username)[0]
    assert must_change == 1

    # The new user is sent to change their password, and can't use other pages until they do
    session = requests.Session()
    response = login(session, username, "Temp1234x")
    assert response.url.endswith("change-password.php")
    assert session.get(url("index.php")).url.endswith("change-password.php")

    # Rules are checked: wrong current password, same password, weak password
    form = {"csrf_token": token(response.text), "submit": "submit"}
    assert "current password is incorrect" in session.post(url("change-password.php"), data={**form, "current_password": "wrong", "password": "NewPass123", "confirm_password": "NewPass123"}).text
    assert "must be different" in session.post(url("change-password.php"), data={**form, "current_password": "Temp1234x", "password": "Temp1234x", "confirm_password": "Temp1234x"}).text
    assert "8 characters" in session.post(url("change-password.php"), data={**form, "current_password": "Temp1234x", "password": "short", "confirm_password": "short"}).text
    response = session.post(url("change-password.php"), data={**form, "current_password": "Temp1234x", "password": "NewPass123", "confirm_password": "NewPass123"})
    assert response.url.endswith("index.php")
    assert sql_value("SELECT must_change_password FROM users WHERE user_id = ?", user_id) == 0

    # A password reset by another user must be changed again, and the update user password form checks its CSRF token
    page = admin.get(url("update-user.php?i=" + user_id))
    before = sql_value("SELECT hashed_password FROM users WHERE user_id = ?", user_id)
    admin.post(url("update-user.php?i=" + user_id), data={"password": "Other1234x", "confirm_password": "Other1234x", "submit_password": "submit_password"})
    assert sql_value("SELECT hashed_password FROM users WHERE user_id = ?", user_id) == before
    admin.post(url("update-user.php?i=" + user_id), data={"password": "Other1234x", "confirm_password": "Other1234x", "submit_password": "submit_password", "csrf_token": token(page.text)})
    assert sql_value("SELECT must_change_password FROM users WHERE user_id = ?", user_id) == 1


def test_failed_logins_lock_out_a_username_then_an_ip_address(admin):
    reset_failed_logins()
    try:
        session = requests.Session()
        for _ in range(5):
            login(session, "admin", "wrong-password")
        assert "too many failed login attempts" in login(session, "admin", ADMIN_PASSWORD).text
        # Other usernames are not affected
        assert login(requests.Session(), "ADMIN".lower() + "x", "anything").status_code == 200
        reset_failed_logins()
        assert login(requests.Session(), "admin", ADMIN_PASSWORD).url.endswith("index.php")

        # 20 failed logins from one address locks out every username from it
        for number in range(20):
            login(requests.Session(), unique("nobody"), "x")
        assert "too many failed login attempts" in login(requests.Session(), "admin", ADMIN_PASSWORD).text
    finally:
        reset_failed_logins()


def test_pages_load_without_php_errors(admin):
    for page in ["index.php", "users.php", "logs.php", "api.php", "add-contact.php", "add-user.php", "add-api.php", "import.php", "change-password.php"]:
        response = admin.get(url(page))
        assert response.status_code == 200 and no_php_errors(response.text), page
