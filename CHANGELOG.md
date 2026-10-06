# Address Book Changelog

## 1.1.0 (Unreleased)

Security fixes. Existing installations must run [sql/upgrades/1.1.0.sql](sql/upgrades/1.1.0.sql), see the README.

- Random IDs, API tokens and CSRF tokens are generated with a cryptographically secure generator (`random_int()`) instead of `rand()`.
- Passwords are hashed with `password_hash()` and checked with `password_verify()`. Existing hashes are upgraded automatically when users log in.
- The session ID is changed when logging in and out, to prevent session fixation.
- The session cookie is set as `HttpOnly` and `SameSite=Lax`, and `Secure` when the site is served over HTTPS. Session IDs are only accepted from cookies and must have been issued by the server.
- The default `admin` account, new users, and users whose password is set by another user must choose a new password at their next login.
- CSRF tokens are compared in constant time with `hash_equals()`.
- Fixed the password form on the update user page not checking the CSRF token.
- API tokens can be sent in an `Authorization: Bearer` header. The `t` GET value still works. Only the first 4 characters of a token are recorded in the logs.
- Failed logins are limited per username and per IP address (configurable with `LOGIN_MAX_FAILED_USERNAME`, `LOGIN_MAX_FAILED_IP` and `LOGIN_LOCKOUT_MINUTES`).
- Database connection errors are recorded in the server error log instead of being shown on the page, and database errors are raised as exceptions and shown as a generic error page.
- Logging out requires a form submission with a CSRF token, so other sites can't log users out with a link.
- Missing or non-text API values (`m`, `q`) no longer cause PHP warnings or errors.
- Pages send `X-Frame-Options`, `Content-Security-Policy: frame-ancestors 'none'`, `X-Content-Type-Options` and `Referrer-Policy` headers.
- Docker: the system connects to MySQL as an `address_book` user with only `SELECT`, `INSERT`, `UPDATE` and `DELETE` access, instead of `root`. The password is set in a `.env` file.
- Docker: Nginx is pinned to the `1.30` stable release instead of `latest`.
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
