# Address Book Changelog

## 1.3.0 (2026-10-10)

- Icons are included in the pages instead of loading the Font Awesome icon font.
- Removed `tools/mysql-to-sqlite.php`, which copied data from the MySQL versions before 1.1.0. The image no longer includes MySQL support.
- The database structure file is now `sql/schema.sql`.
- New screenshots, including dark mode and phones, which `tests/screenshots.sh` takes again when needed.
- Images are no longer published with `sha-` tags, and a weekly workflow deletes old untagged images and earlier `sha-` tags from the registry.

## 1.2.0 (2026-10-10)

- Added importing contacts from vCard (`.vcf`) files, such as from a phone, Google Contacts or iCloud, and from CSV files, and exporting contacts as CSV or vCard files. Contacts already in the address book are skipped, and any which can't be added are listed with the reason.
- Phone numbers can be typed in any common format (such as `+1 (212) 555-1234`), are stored as digits, and are shown in US format by default. The `PHONE_FORMAT` setting chooses `us`, `uk` or `none`. Existing phone numbers are converted when the system is updated.
- The API's `findNumber` method finds numbers in any format, with or without a country code.
- Users stay logged in: logins last `SESSION_LIFETIME_DAYS` (365 by default) from the last visit, and are kept next to the database so that restarting or updating the system doesn't log users out. API calls no longer create sessions.
- Log entries older than `LOG_RETENTION_DAYS` (90 by default) are removed automatically, and the Logs page loads entries a page at a time, so it stays fast as the logs grow.
- Only a contact's first name is needed, so that contacts without an address (such as imported ones) can be added and updated.
- Fixed clearing the first name of a contact clearing its middle name instead, and fixed optional fields (such as phone numbers and email addresses) not being cleared when emptied in the update contact form.
- The database records its version and upgrades itself when the system is updated.
- Added automated tests (see the README), which run on every pull request and before each image is published.
- The image is also published with version tags (such as `1.2.0`, `1.2` and `1`) as well as `latest`.
- The TrueNAS YAML file keeps the database in a dataset, so that it is included in snapshots.

## 1.1.0 (2026-10-08)

The system now stores its data in SQLite instead of MySQL/MariaDB, and includes security fixes.

- Data is stored in a single SQLite database file instead of a MySQL/MariaDB server. The file and its tables are created automatically on first use. The `DB_SERVER`, `DB_USER`, `DB_PASS` and `DB_NAME` settings are replaced by `DB_PATH`, which is optional.
- Added `tools/mysql-to-sqlite.php` to copy an existing MySQL database into SQLite (removed in 1.3.0).
- Usernames are not case sensitive, as before (`Admin` and `admin` are the same user).
- Random IDs, API tokens and CSRF tokens are generated with a cryptographically secure generator (`random_int()`) instead of `rand()`.
- Passwords are hashed with `password_hash()` and checked with `password_verify()`. Existing hashes are upgraded automatically when users log in.
- The session ID is changed when logging in and out, to prevent session fixation.
- The session cookie is set as `HttpOnly` and `SameSite=Lax`, and `Secure` when the site is served over HTTPS. Session IDs are only accepted from cookies and must have been issued by the server.
- The default `admin` account, new users, and users whose password is set by another user must choose a new password at their next login.
- CSRF tokens are compared in constant time with `hash_equals()`.
- Fixed the password form on the update user page not checking the CSRF token.
- Fixed the username entered on the login page being shown back without escaping (reflected cross-site scripting).
- API tokens can be sent in an `Authorization: Bearer` header. The `t` GET value still works. Only the first 4 characters of a token are recorded in the logs.
- Failed logins are limited per username and per IP address (configurable with `LOGIN_MAX_FAILED_USERNAME`, `LOGIN_MAX_FAILED_IP` and `LOGIN_LOCKOUT_MINUTES`).
- Database errors are recorded in the server error log instead of being shown on the page, and a generic error page is shown instead.
- Logging out requires a form submission with a CSRF token, so other sites can't log users out with a link.
- Missing or non-text API values (`m`, `q`) no longer cause PHP warnings or errors.
- Pages send `X-Frame-Options`, `Content-Security-Policy: frame-ancestors 'none'`, `X-Content-Type-Options` and `Referrer-Policy` headers.
- Docker: a single container runs the system on Apache and PHP `8.5`, replacing the separate Nginx, PHP-FPM and MySQL containers. The database is kept in a Docker volume.
- Updated Bootstrap from 3.4.1 to 5.3.8 and DataTables from 1.10.15 to 3.1.3, and removed jQuery, which neither needs. This fixes the navigation menu not opening on small screens, which broke when jQuery was updated to 3.5.0.
- Pages now scale correctly on phones, and wide tables scroll within the page instead of making the whole page scroll sideways.
- Added support for running as a TrueNAS SCALE custom app: an image of the system published to the GitHub Container Registry by GitHub Actions, and a ready-to-use [TrueNAS YAML file](truenas/address-book.yaml).
- Settings can be set as environment variables as well as in `settings.local.inc.php`.
- Added a dark theme. It follows the device's light/dark setting, and a button in the navigation bar switches between Auto, Dark and Light (remembered in the browser).
- Fixed PHP 8.1+ deprecation notices when viewing, updating or deleting a contact without an email address or phone number, and when a form is submitted with fields missing.
- Documented running behind a reverse proxy.
- Fixed site functions and messages being loaded after the session and user checks, which caused an error when a logged in user was logged out for a failed security check.

## 1.0.6 (2025-03-28)

- Docker PHP support updated version `8.4` ([#8](https://github.com/AlexWinder/address-book/issues/8)).
- Docker MySQL support updated to version `9`.
- Fixed incorrect redirect to index page after creating a new user.

## 1.0.5 (2025-01-05)

- Added timezone support by [@zaydons](https://github.com/zaydons) in https://github.com/AlexWinder/address-book/pull/6.

## 1.0.4 (2022-08-03)

- Updated README to include support for `docker compose` on top of `docker-compose`.
- Fixed README with a typo on the `DB_PASS` value.
- API calls returned with the correct header of `Content-Type: application/json`.
- API calls returned with the correct HTTP status code rather than all being returned as HTTP 200 OK.

## 1.0.3 (2022-07-17)

- Added Docker build environment.
- Updated README file instructions and markdown formatting.

## 1.0.2 (2020-04-30)

- Updated jQuery to 3.5.0 to address [CVE-2020-11022](https://github.com/advisories/GHSA-gxr4-xjj5-5px2).
- Updated Bootstrap to 3.4.1.

## 1.0.1 (2019-12-26)

- Added missing DataTables sort images.
- Added missing Bootstrap map files.
- Fixed Bootstrap directory name causing issues on some browsers not loading assets.

## 1.0.0 (2018-05-24)

- Initial release of system.
