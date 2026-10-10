"""
Shared set-up for the tests. The tests run against the system running in a Docker container - see tests/run.sh,
which starts one and sets these environment variables:

  BASE_URL       the address of the system, such as http://127.0.0.1:8080/
  APP_CONTAINER  the name of the container, used to look at the database directly
  IMAGE          the image being tested, used by tests which start containers of their own
"""
import json
import os
import re
import subprocess
import uuid

import pytest
import requests

BASE_URL = os.environ.get("BASE_URL", "http://127.0.0.1:8080/")
APP_CONTAINER = os.environ.get("APP_CONTAINER", "address-book-test")
IMAGE = os.environ.get("IMAGE", "address-book:test")

DEFAULT_ADMIN_PASSWORD = "LetMeIn123"
ADMIN_PASSWORD = "TestAdmin123"


def url(path=""):
    return BASE_URL + path


def token(html):
    """The CSRF token in a page's form."""
    match = re.search(r'name="csrf_token" value="([^"]+)"', html)
    assert match, "no CSRF token on the page"
    return match.group(1)


def login(session, username, password):
    """Log in, returning the response (which follows the redirect after logging in)."""
    page = session.get(url("login.php"))
    return session.post(url("login.php"), data={"username": username, "password": password, "csrf_token": token(page.text), "submit": "submit"})


def sql(query, *parameters):
    """Run a query on the system's SQLite database, returning the rows as lists of strings."""
    php = (
        '$d = new PDO("sqlite:" . getenv("DB_PATH")); $d->exec("PRAGMA busy_timeout = 5000");'
        '$s = $d->prepare($argv[1]); $s->execute(array_slice($argv, 2));'
        'if ($s->columnCount()) foreach ($s->fetchAll(PDO::FETCH_NUM) as $r) echo json_encode($r), "\\n";'
    )
    result = subprocess.run(["docker", "exec", "-u", "www-data", APP_CONTAINER, "php", "-r", php, query, *map(str, parameters)],
                            capture_output=True, text=True, check=True)
    return [json.loads(line) for line in result.stdout.splitlines() if line]


def sql_value(query, *parameters):
    rows = sql(query, *parameters)
    return rows[0][0] if rows else None


def unique(prefix="t"):
    """A unique name, so that tests don't depend on each other's data."""
    return prefix + uuid.uuid4().hex[:8]


def no_php_errors(html):
    return not re.search(r"(Warning|Deprecated|Fatal error|Notice)</b>:|PHP (Warning|Deprecated|Fatal)", html)


def reset_failed_logins():
    """Move failed logins out of the lockout window, so that one test's failed logins don't lock out the next test."""
    sql("UPDATE logs SET datetime = datetime(datetime, '-1 day') WHERE action LIKE 'Login Failed%'")


@pytest.fixture(scope="session", autouse=True)
def app_ready():
    """Load a page before any test runs, so that the system has created its database."""
    assert requests.get(url("login.php")).status_code == 200


@pytest.fixture(scope="session")
def first_admin_login():
    """
    Log in as the default admin for the first time, which must ask for a new password, then change it.
    Returns the response from the first login, so that tests can check that a password change was asked for.
    On a database where this has already happened, logs in with the new password instead.
    """
    reset_failed_logins()
    session = requests.Session()
    response = login(session, "admin", DEFAULT_ADMIN_PASSWORD)
    if "change-password.php" in response.url:
        changed = session.post(url("change-password.php"), data={
            "current_password": DEFAULT_ADMIN_PASSWORD, "password": ADMIN_PASSWORD, "confirm_password": ADMIN_PASSWORD,
            "csrf_token": token(response.text), "submit": "submit"})
        assert changed.url.endswith("index.php"), changed.url
    return response


@pytest.fixture
def admin(first_admin_login):
    """A logged in session for the admin user."""
    reset_failed_logins()
    session = requests.Session()
    response = login(session, "admin", ADMIN_PASSWORD)
    assert response.url.endswith("index.php"), response.url
    return session


def add_contact(session, **fields):
    """Add a contact through the form, returning the response. A unique first name is used if none is given."""
    data = {field: "" for field in ["first_name", "middle_name", "last_name", "contact_number_home", "contact_number_mobile", "contact_email",
                                    "date_of_birth", "address_line_1", "address_line_2", "address_town", "address_county", "address_post_code"]}
    data["first_name"] = unique("Contact")
    data.update(fields)
    data["csrf_token"] = token(session.get(url("add-contact.php")).text)
    data["submit"] = "submit"
    return session.post(url("add-contact.php"), data=data)


def contact_id(first_name):
    return sql_value("SELECT contact_id FROM contacts WHERE first_name = ?", first_name)
