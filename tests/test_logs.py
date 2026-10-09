"""The Logs page, which loads entries a page at a time, and the removal of old entries."""
import requests

from conftest import sql, sql_value, unique, url


def test_logs_are_loaded_a_page_at_a_time(admin):
    page = admin.get(url("logs.php"))
    assert 'id="logs"' in page.text and "logs-data.php" in page.text

    response = admin.get(url("logs-data.php"), params={"draw": 3, "start": 0, "length": 10, "order[0][column]": 0, "order[0][dir]": "desc"})
    data = response.json()
    assert data["draw"] == 3 and data["recordsTotal"] >= 1 and data["recordsTotal"] == data["recordsFiltered"]
    assert 1 <= len(data["data"]) <= 10 and len(data["data"][0]) == 4
    # Newest first
    dates = [row[0] for row in data["data"]]
    assert dates == sorted(dates, reverse=True)


def test_logs_can_be_searched_and_values_are_escaped(admin):
    marker = unique("<b>Search")
    sql("INSERT INTO logs (datetime, action, url, user, ip, user_agent) VALUES (datetime('now'), ?, '', 'Test', '203.0.113.9', '')", marker)
    data = admin.get(url("logs-data.php"), params={"draw": 1, "start": 0, "length": 10, "search[value]": marker[3:]}).json()
    assert data["recordsFiltered"] == 1
    assert data["data"][0][1] == "&lt;b&gt;" + marker[3:]
    # % and _ are searched for as ordinary characters
    assert admin.get(url("logs-data.php"), params={"draw": 1, "start": 0, "length": 10, "search[value]": "%%%_%%"}).json()["recordsFiltered"] == 0


def test_logs_need_login():
    response = requests.get(url("logs-data.php"))
    assert response.status_code == 401


def test_old_log_entries_are_removed(admin):
    old = unique("Old entry")
    recent = unique("Recent entry")
    sql("INSERT INTO logs (datetime, action) VALUES (datetime('now', '-91 days'), ?)", old)
    sql("INSERT INTO logs (datetime, action) VALUES (datetime('now', '-89 days'), ?)", recent)
    # Viewing the Logs page removes entries older than LOG_RETENTION_DAYS (90 by default)
    admin.get(url("logs.php"))
    assert sql_value("SELECT COUNT(*) FROM logs WHERE action = ?", old) == 0
    assert sql_value("SELECT COUNT(*) FROM logs WHERE action = ?", recent) == 1
