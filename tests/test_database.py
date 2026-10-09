"""The SQLite database: concurrent use, safety, upgrades from older versions, and keeping data when the container is replaced."""
import os
import re
import subprocess
import tempfile
import time
from concurrent.futures import ThreadPoolExecutor

import pytest
import requests

from conftest import APP_CONTAINER, IMAGE, login, sql, sql_value, token, unique, url


def docker(*args, check=True):
    return subprocess.run(["docker", *args], capture_output=True, text=True, check=check)


def free_port():
    import socket
    with socket.socket() as s:
        s.bind(("127.0.0.1", 0))
        return s.getsockname()[1]


def start_app(volume_or_path, port):
    """Start a container of the image being tested with the given data volume or directory, and wait for it to answer."""
    name = unique("ab-test-")
    docker("run", "-d", "--name", name, "-p", "127.0.0.1:%d:80" % port, "-v", volume_or_path + ":/data",
           "-e", "SITE_URL=http://127.0.0.1:%d/" % port, "-e", "TIMEZONE=UTC", IMAGE)
    for _ in range(60):
        try:
            requests.get("http://127.0.0.1:%d/css/main.css" % port, timeout=2)
            return name
        except requests.ConnectionError:
            time.sleep(0.5)
    raise RuntimeError("container did not start: " + docker("logs", name, check=False).stdout)


def test_many_requests_at_once(admin):
    cookies = admin.cookies.get_dict()
    before = sql_value("SELECT COUNT(*) FROM logs")

    def load(number):
        response = requests.get(url(["index.php", "users.php", "logs.php", "api.php"][number % 4]), cookies=cookies)
        return response.status_code, "Unable to open the database" in response.text or "unexpected error" in response.text

    with ThreadPoolExecutor(max_workers=20) as pool:
        results = list(pool.map(load, range(100)))
    assert all(result == (200, False) for result in results), [r for r in results if r != (200, False)][:5]
    # Every page view was logged (other tests may run at the same time, so at least 100)
    assert sql_value("SELECT COUNT(*) FROM logs") - before >= 100
    logs = docker("logs", APP_CONTAINER, check=False)
    assert "database is locked" not in (logs.stdout + logs.stderr)


def test_database_is_healthy_and_in_wal_mode(admin):
    assert sql_value("PRAGMA integrity_check") == "ok"
    assert sql_value("PRAGMA journal_mode") == "wal"
    assert sql_value("PRAGMA user_version") == 2


@pytest.mark.parametrize("path", ["data/address-book.sqlite", "../data/address-book.sqlite", "address-book.sqlite",
                                  "..%2Fdata%2Faddress-book.sqlite", "sql/sql.sql", "../includes/settings.config.inc.php", "../tools/mysql-to-sqlite.php"])
def test_files_outside_the_web_folder_cannot_be_downloaded(path):
    response = requests.get(url(path))
    assert response.status_code in (400, 403, 404) and "SQLite format" not in response.text


def test_upgrade_from_a_110_database_and_data_is_kept_when_the_container_is_replaced():
    """A database from version 1.1.0 (user_version 0, phone numbers with spaces) is upgraded when the new version opens it."""
    volume = unique("ab-test-data-")
    docker("volume", "create", volume)
    names = []
    try:
        # Create a 1.1.0 database: the original structure, without the logs_datetime index, with old-style phone numbers
        create = (
            '$d = new PDO("sqlite:/data/address-book.sqlite");'
            '$sql = file_get_contents("/var/www/address-book/sql/sql.sql");'
            '$sql = preg_replace("/CREATE INDEX IF NOT EXISTS logs_datetime[^;]*;/", "", $sql);'
            '$d->exec($sql);'
            '$d->exec("INSERT INTO contacts (contact_id, first_name, contact_number_home, contact_number_mobile) VALUES (\'old000000001\', \'Old\', \'01234 567890\', \'+1 212-555-0123\')");'
        )
        docker("run", "--rm", "-u", "www-data", "-v", volume + ":/data", IMAGE, "php", "-r", create)

        port = free_port()
        names.append(start_app(volume, port))
        requests.get("http://127.0.0.1:%d/login.php" % port)
        check = ('$d = new PDO("sqlite:/data/address-book.sqlite");'
                 'echo $d->query("PRAGMA user_version")->fetchColumn(), "|", implode(",", $d->query("SELECT contact_number_home || \'/\' || contact_number_mobile FROM contacts")->fetchAll(PDO::FETCH_COLUMN)), "|",'
                 '$d->query("SELECT COUNT(*) FROM sqlite_master WHERE name = \'logs_datetime\'")->fetchColumn();')
        assert docker("exec", "-u", "www-data", names[0], "php", "-r", check).stdout == "2|01234567890/+12125550123|1"

        # Log in and change the admin password, then replace the container: the data and the login must still be there
        session = requests.Session()
        base = "http://127.0.0.1:%d/" % port
        page = session.get(base + "login.php")
        response = session.post(base + "login.php", data={"username": "admin", "password": "LetMeIn123", "csrf_token": token(page.text), "submit": "submit"})
        session.post(base + "change-password.php", data={"current_password": "LetMeIn123", "password": "Replace123", "confirm_password": "Replace123",
                                                         "csrf_token": token(response.text), "submit": "submit"})
        docker("rm", "-f", names[0])
        names.append(start_app(volume, port))
        # Sessions are kept in the data volume, so the user is still logged in after the container is replaced
        assert session.get(base + "index.php").url.endswith("index.php")
        assert "Old" in session.get(base + "index.php").text
    finally:
        for name in names:
            docker("rm", "-f", name, check=False)
        docker("volume", "rm", volume, check=False)


def test_a_directory_owned_by_root_is_made_writable():
    """Such as a newly created TrueNAS dataset."""
    directory = tempfile.mkdtemp(prefix="ab-test-dataset-")
    name = None
    try:
        port = free_port()
        name = start_app(directory, port)
        assert requests.get("http://127.0.0.1:%d/login.php" % port).status_code == 200
        assert docker("exec", name, "stat", "-c", "%U", "/data/address-book.sqlite").stdout.strip() == "www-data"
    finally:
        if name:
            docker("rm", "-f", name, check=False)
        docker("run", "--rm", "-v", directory + ":/data", "--entrypoint", "sh", IMAGE, "-c", "rm -rf /data/*", check=False)
        os.rmdir(directory)


MYSQL_IMAGE = os.environ.get("MYSQL_IMAGE")


@pytest.mark.skipif(not MYSQL_IMAGE, reason="set MYSQL_IMAGE (such as mysql:9) to test copying data from MySQL")
@pytest.mark.parametrize("schema,charset,options", [
    ("tests/fixtures/mysql-1.0.6.sql", "utf8mb4", []),
    ("tests/fixtures/mysql-1.0.6.sql", "latin1", ["--charset=latin1"]),
])
def test_copying_a_mysql_database(schema, charset, options):
    network = unique("ab-test-net-")
    mysql = unique("ab-test-mysql-")
    volume = unique("ab-test-data-")
    docker("network", "create", network)
    try:
        docker("run", "-d", "--name", mysql, "--network", network, "-e", "MYSQL_ROOT_PASSWORD=rootpw", MYSQL_IMAGE)
        for _ in range(90):
            if docker("exec", mysql, "mysqladmin", "ping", "-h", "127.0.0.1", "-uroot", "-prootpw", "--silent", check=False).returncode == 0:
                break
            time.sleep(2)
        root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
        for script, client_charset in [(schema, "utf8mb4"), ("tests/fixtures/mysql-data.sql", charset)]:
            subprocess.run(["docker", "exec", "-i", mysql, "mysql", "--default-character-set=" + client_charset, "-uroot", "-prootpw"],
                           stdin=open(os.path.join(root, script), "rb"), check=True, capture_output=True)

        command = ["run", "--rm", "-u", "www-data", "--network", network, "-v", volume + ":/data", IMAGE, "php", "/var/www/address-book/tools/mysql-to-sqlite.php",
                   "--host=" + mysql, "--user=root", "--password=rootpw", "--database=address_book", "--output=/data/address-book.sqlite", *options]
        output = docker(*command).stdout
        assert "contacts  2 rows copied" in output and "users     2 rows copied" in output
        # Running it again doesn't overwrite the new database
        assert docker(*command, check=False).returncode == 1

        check = ('$d = new PDO("sqlite:/data/address-book.sqlite");'
                 'echo bin2hex($d->query("SELECT first_name FROM contacts WHERE last_name LIKE \'Bront%\'")->fetchColumn()), "|",'
                 '$d->query("SELECT must_change_password FROM users WHERE username = \'admin\'")->fetchColumn(), "|",'
                 '$d->query("SELECT api_id FROM api")->fetchColumn();')
        result = docker("run", "--rm", "-u", "www-data", "-v", volume + ":/data", IMAGE, "php", "-r", check).stdout
        # "Zoë" in UTF-8, the default admin is asked to change its password, and the API token is kept
        assert result == "5a6fc3ab|1|Pz7Kq2Lm9Xa4"

        # The copied user can log in with their old password
        port = free_port()
        name = start_app(volume, port)
        try:
            session = requests.Session()
            page = session.get("http://127.0.0.1:%d/login.php" % port)
            response = session.post("http://127.0.0.1:%d/login.php" % port, data={"username": "jsmith", "password": "Custom1234x", "csrf_token": token(page.text), "submit": "submit"})
            assert response.url.endswith("index.php")
        finally:
            docker("rm", "-f", name, check=False)
    finally:
        docker("rm", "-f", mysql, check=False)
        docker("volume", "rm", volume, check=False)
        docker("network", "rm", network, check=False)
