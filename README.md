# Address Book

Address Book is a simple PHP-based contact list manager with authentication, logging features and an API to allow integration with other services. Built using the [Bootstrap](https://getbootstrap.com/) 5 framework, [DataTables](https://datatables.net/) and [FontAwesome](https://fontawesome.com/) to maintain a user-friendly functionality.

## Installation

The system stores its data in a single [SQLite](https://sqlite.org/) database file, so no separate database server is needed. The file and its tables are created automatically the first time the system is used.

There are 3 methods to installation: Docker, manual installation on a web server with PHP, or as a custom app on TrueNAS SCALE.

### 1. Docker (Recommended)

Docker is the recommended method to set up this system due to it's ease of getting things configured quickly and also it is far less likely to be a victim of issues which may occur due to OS or versions of software. The below assumes that you have `docker` and `docker compose` installed on your system.

1. Build and start the system:

```bash
docker compose up --build
```

2. Visit http://localhost/ and log in (see [Testing](#testing)).

[docker-compose.yml](docker-compose.yml) builds the same image as the [TrueNAS app](#3-truenas-scale), with the code mounted from this directory so that changes are seen straight away. The database is kept in the `address-book-data` Docker volume. Settings are set as environment variables in `docker-compose.yml`, or in an `includes/settings.local.inc.php` file (see [Local Settings Configuration Values](#local-settings-configuration-values)).

### 2. Manual Installation

If you wish to set up the system manually then this too can be done.

#### Requirements

- A web server with PHP (8+ recommended).
- The `pdo_sqlite` PHP module, which is included with most PHP installations. For example on Debian or Ubuntu with PHP 8.5, install `php8.5-sqlite3`.

#### Settings Configuration

Create a copy of the [EXAMPLE.settings.local.inc.php](includes/EXAMPLE.settings.local.inc.php) with the name of `settings.local.inc.php` in the [includes](includes/) directory, and set its values to match your environment (see [Local Settings Configuration Values](#local-settings-configuration-values)). For example, on a Linux system:

```bash
cp includes/EXAMPLE.settings.local.inc.php includes/settings.local.inc.php
```

#### Database

By default the database is `data/address-book.sqlite`, in the directory above [html](html/). Create the `data` directory and give the web server user permission to write to it. SQLite also creates temporary files next to the database, so the web server needs to be able to write to the directory and not just the file. For example, where the web server runs as `www-data`:

```bash
mkdir data
chown www-data:www-data data
```

To keep the database elsewhere, set `DB_PATH`. Keep it outside of the [html](html/) directory, so that it can't be downloaded. Don't put it on a network share (such as NFS or SMB), as SQLite needs a local disk to work reliably.

#### Web Server Configuration

You should configure your web server so that the document root is set as the [html](html) directory. However, the web server user for your configuration should have access to the [html](html/), [includes](includes/) and [sql](sql/) directories, and write access to the database directory.

### 3. TrueNAS SCALE

The system can run as a custom app on TrueNAS SCALE 24.10 (Electric Eel) or later. It uses a prebuilt image, `ghcr.io/zaydons/address-book`, which GitHub Actions builds and publishes to the GitHub Container Registry (see [.github/workflows/docker-publish.yml](.github/workflows/docker-publish.yml)). The image runs the system on Apache and PHP 8.5, with the SQLite database in its `/data` directory.

No files need to be copied to TrueNAS. The system is configured with environment variables instead of a `settings.local.inc.php` file.

#### Publishing the image (once)

1. Push to the `main` branch, or run the **Test and publish** workflow from the **Actions** tab on GitHub. The image is only published if the tests pass. On a fork, you may need to enable GitHub Actions in the **Actions** tab first.
2. On GitHub, open the `address-book` package (from **Packages** on your profile) and check under **Package settings** that the visibility is **Public**, so that TrueNAS can download it. If you keep it private, add your GitHub username and a personal access token with the `read:packages` scope as a registry login in TrueNAS instead.

#### Installing the app

1. Create a dataset for the app's data, such as `SSDs/Applications/address-book` (**Datasets → Add Dataset**). It can be empty: the app sets its permissions when it starts.
2. In TrueNAS, go to **Apps**, then **Discover Apps**, open the menu (three dots) and choose **Install via YAML**.
3. Give the app a name, such as `address-book`.
4. Paste in the contents of [truenas/address-book.yaml](truenas/address-book.yaml), and before saving change:
   - the dataset path on the `volumes` line to the dataset you created (`/mnt/<pool>/...`),
   - `SITE_URL` to the address you will use, such as `http://192.168.1.10:8080/`,
   - `TIMEZONE` to your timezone, such as `America/New_York` (see the [list of timezones](https://www.php.net/manual/en/timezones.php)).
5. Save, then open the `SITE_URL` address and log in with the default credentials below. You will be asked to choose a new password.

The database (`address-book.sqlite`) and logged-in sessions are kept in the dataset, so they survive app updates and restarts, and are included in snapshots of the dataset.

TrueNAS uses ports 80 and 443 for its own web interface, so the system uses port 8080. To use a different port, change the first number in `"8080:80"`, and in `SITE_URL`. To see which ports are already in use, run `sudo ss -tlnp` in the TrueNAS shell.

#### Updating

The image is published with these tags:

- `latest` - the newest version. To update, edit the app in TrueNAS and save it again, or use the app's option to pull the latest images.
- `1.2.0` - exactly that version.
- `1.2` and `1` - the newest version starting with that number. For example, `ghcr.io/zaydons/address-book:1` gets fixes and new features, but not a future version 2 which might need changes to your set-up.

To stay on a version until you choose to update, replace `latest` in the `image` line with a version tag. The [CHANGELOG](CHANGELOG.md) lists what each version changes.

New versions are created with the **Release** workflow: in the **Actions** tab on GitHub, choose **Release**, then **Run workflow**, and enter the version number. It creates the GitHub release and tag, with the notes from that version's section of the CHANGELOG, then publishes the image with the version's tags.

For HTTPS, put the system behind a reverse proxy (see [Running Behind a Reverse Proxy](#running-behind-a-reverse-proxy)) and set `SITE_URL` to the `https://` address.

### Testing

Once you have finished running one of the above methods you can then test if the system is working by visiting the address of the server in a browser.

If the system is working correctly then you should be prompted with a login window. The default credentials for the system are:

- Username: `admin`
- Password: `LetMeIn123`

You will be asked to choose a new password the first time you log in. Users added by another user, or whose password is reset by another user, are also asked to choose a new password when they next log in.

## Backups

The database is a single file. To back it up while the system is running, use SQLite's backup command rather than copying the file, as a copy taken during a write may be incomplete. For example, on TrueNAS, where the container is called `address-book` (run `sudo docker ps` to see the names of your containers):

```bash
sudo docker exec -u www-data address-book php -r '$db = new PDO("sqlite:/data/address-book.sqlite"); $db->exec("VACUUM INTO \"/data/backup.sqlite\"");'
```

This writes a complete copy to `backup.sqlite` in the same directory. If the database is in a TrueNAS dataset, snapshots of the dataset are also a good way to keep backups.

Contacts can also be downloaded as a CSV or vCard file from the **Export** button on the Address Book page (see [Importing and Exporting Contacts](#importing-and-exporting-contacts)). This keeps the contacts, but not users, API tokens or logs.

## Local Settings Configuration Values

There are several configuration values which can be set in the `includes/settings.local.inc.php` file. Each of them can also be set as an environment variable with the same name, which is how the [Docker](#1-docker-recommended) and [TrueNAS](#3-truenas-scale) installations are configured. A value in `settings.local.inc.php` takes priority over an environment variable. Below lists settings with their appropriate values:

- `DB_PATH` (optional) is the location of the SQLite database file. It defaults to `data/address-book.sqlite` in the directory above `html/`, or `/data/address-book.sqlite` in the Docker image.
- `SITE_URL` is the address used to access the system.

- `TIMEZONE` should be set to the timezone you require for the system. See the [PHP Manual](https://www.php.net/manual/en/timezones.php) for options. 
- `LOGIN_MAX_FAILED_USERNAME` (optional, default `5`) is the number of failed logins allowed for one username within the lockout window. Further attempts for that username are blocked until the window has passed.
- `LOGIN_MAX_FAILED_IP` (optional, default `20`) is the number of failed logins allowed from one IP address within the lockout window.
- `LOGIN_LOCKOUT_MINUTES` (optional, default `15`) is the length of the lockout window in minutes.
- `PHONE_FORMAT` (optional, default `us`) is how phone numbers are shown: `us`, `uk` or `none` (see [Phone Numbers](#phone-numbers)).
- `SESSION_LIFETIME_DAYS` (optional, default `365`) is how long users stay logged in. Each visit starts the count again, so users who keep using the system stay logged in. Set it to `0` to log users out when they close their browser. Logged-in sessions are kept next to the database (in a `sessions` directory), so restarting or updating the system doesn't log users out.
- `LOG_RETENTION_DAYS` (optional, default `90`) is how many days log entries are kept for. Older entries are removed automatically. Set it to `0` to keep them forever.

## Importing and Exporting Contacts

On the Address Book page:

- **Export** downloads every contact as a **CSV** file, which opens in spreadsheet programs such as Excel, or a **vCard** (`.vcf`) file, which can be imported into phones and other address books.
- **Import** adds contacts from a vCard file (exported from a phone, Google Contacts, iCloud, Outlook or another address book) or a CSV file. A CSV file needs a header row naming its columns. Use the same columns as an exported CSV file, although common alternatives such as "Given Name", "Surname", "Cell" and "Zip" are recognised too. Dates of birth are written as `YYYY-MM-DD`.

Imported contacts are checked in the same way as contacts added by hand. Any which can't be added, such as one with an invalid email address, are listed with the reason so that they can be fixed. Contacts which are already in the address book (the same name, phone numbers and email address) are skipped, unless you choose otherwise.

## Phone Numbers

Phone numbers can be typed in any common format, such as `+1 (212) 555-1234`, `212.555.1234` or `07700 900123`. They are stored as digits, with a `+` at the start for an international number, and shown in the format set by `PHONE_FORMAT`:

- `us` (the default) - `(212) 555-1234`, and `+1 (212) 555-1234` for numbers with the +1 country code.
- `uk` - `01234 567890`.
- `none` - as stored.

Numbers which don't fit the format, such as international numbers, are shown as stored.

## Dark Theme

The system follows the light or dark setting of the user's device. The theme button in the navigation bar switches between **Auto** (follow the device), **Dark** and **Light**. The choice is remembered in that browser.

## Running Behind a Reverse Proxy

The system uses the client's IP address (`REMOTE_ADDR`) to check that a logged in session hasn't moved to another device, to limit failed logins, to check API tokens which are restricted to an IP address, and in the logs. If the system is behind a reverse proxy or load balancer, `REMOTE_ADDR` will be the proxy's address unless the web server is configured to replace it with the real client address. Without that:

- All users will appear to come from the same IP address, so failed logins from anyone count towards the same IP address limit.
- API tokens restricted to an IP address will only work if restricted to the proxy's address.

Configure your web server to take the client address from the proxy, but only trust the header from your proxy's address. For example, with Nginx's [realip module](https://nginx.org/en/docs/http/ngx_http_realip_module.html):

```nginx
set_real_ip_from 10.0.0.0/8;  # your proxy's address
real_ip_header X-Forwarded-For;
```

or with Apache's [mod_remoteip](https://httpd.apache.org/docs/current/mod/mod_remoteip.html):

```apache
RemoteIPHeader X-Forwarded-For
RemoteIPInternalProxy 10.0.0.0/8
```

If the proxy handles HTTPS, set `SITE_URL` to the `https://` address so that the session cookie is only sent over HTTPS.

## Screenshots

Screenshots of the system can be found in the [screenshots](screenshots/) directory, or by viewing the [SCREENSHOTS.md](SCREENSHOTS.md) file.

## API

The API built in the system is accessed using a HTTP GET request to the [api.php](html/api.php) page. The API token is sent in an `Authorization` header, and the request requires 2 values:

- `m` for the API method.
- `q` for the API query string.

For example:

```bash
curl -H "Authorization: Bearer APITOKEN" "http://localhost/api.php?m=APIMETHOD&q=APIQUERY"
```

For older integrations the token can still be sent as a `t` value instead, such as `http://localhost/api.php?t=APITOKEN&m=APIMETHOD&q=APIQUERY`. This isn't recommended, as web servers and proxies record URLs in their logs. The system's own logs only record the first 4 characters of a token.

API tokens are created on the same [api.php](html/api.php) page.

Results of an API call are returned in a JSON array with the following indexes:

- `success` - This is set to `0` by default, unless the API call is successful in which case it will be set to `1`.
- `method` - The method used as part of the API call. This will only return valid methods (see below). This is the `m` value in the HTTP GET request.
- `query` - The query used against the method. This is the `q` value in the HTTP GET request.
- `result` - The result of the API call, if any.
- `result_message` - Used primarily for troubleshooting, such as if a token or method is valid.

```json
{
    "success": 0,
    "method": null,
    "query": null,
    "result": "invalid_token",
    "result_message": "An invalid API token was sent. This means that the token does not exist or you are making an API call from an unauthorised IP address."
}
```

```json
{
    "success": 0,
    "method": null,
    "query": null,"
    result": "invalid_method",
    "result_message": "An invalid API method was requested. Please follow the documentation and check your requested method exists, this includes correct spelling and upper\/lower case characters."
}
```

```json
{
    "success": 0,
    "method": "findNumber",
    "query": "01189998819991197253",
    "result": "no_result",
    "result_message": "A result could not be found."
} 
```

```json
{
    "success": 1,
    "method": "findNumber",
    "query": "156421616",
    "result": "William Shakespeare",
    "result_message": "API call successful."
}
```

### API Notes

If an API token has no authorised IP address associated with it, then this means that the token can be used from any IP address. If this is not intended then specify an IP address when creating the API token.

### API Methods

API methods are used in the `m` value in the HTTP GET request. The following methods are valid.

- `findNumber` - Obtain the first contact found based on a queried phone number (mobile and home). The number can be in any format, such as `2125551234` or `+1 (212) 555-1234` (URL-encoded). Numbers of 10 or more digits also match on their last 10 digits, so a number is found with or without its country code (for example `+12125551234` finds a contact saved as `(212) 555-1234`). If more than one contact has the number, an exact match comes first, then the contacts in alphabetical order of last name.
  - Example: a query of `api.php?m=findNumber&q=0987654321` (with the token in the `Authorization` header) will return the result (if it exists) for the phone number `0987654321`.

## License

This project is licensed under the [MIT License](LICENSE.md).

## Contributions

### Running the Tests

The tests run against the system in a fresh Docker container. They cover logging in, security, contacts, phone numbers, the API, importing and exporting, logs, the database, and the pages in a browser (light and dark themes, and on a phone). They run automatically on every pull request and before each image is published.

To run them yourself, you need Docker, Python 3 and Node.js:

```bash
pip install -r tests/requirements.txt
(cd tests/ui && npm ci && npx playwright install --with-deps chromium)
tests/run.sh
```

Set `SKIP_UI=1` to skip the browser tests.

`tests/screenshots.sh` takes new screenshots for [SCREENSHOTS.md](SCREENSHOTS.md), of the system with example contacts, in the same way.

Pull requests (PRs) to this repository are welcome. If your PR is to address an open issue, please try to keep your changes specific to only that issue. Also please avoid addressing multiple issues within a single PR.
