# Address Book

Address Book is a simple PHP-based contact list manager with authentication, logging features and an API to allow integration with other services. Built using the [Bootstrap](https://getbootstrap.com/) 5 framework, [DataTables](https://datatables.net/) and [FontAwesome](https://fontawesome.com/) to maintain a user-friendly functionality.

## Installation

There are 2 methods to installation. Regardless of which method you choose you should first complete some prerequisites.

Create a copy of the [EXAMPLE.settings.local.inc.php](includes/EXAMPLE.settings.local.inc.php) with the name of `settings.local.inc.php`. This file should exist in the [includes](includes/) directory. In this file are a number of different values which can be set based on your environment.

For example, on a Linux system:

```bash
cp includes/EXAMPLE.settings.local.inc.php includes/settings.local.inc.php
```

### 1. Docker (Recommended)

Docker is the recommended method to set up this system due to it's ease of getting things configured quickly and also it is far less likely to be a victim of issues which may occur due to OS or versions of software. The below assumes that you have `docker` and `docker compose` installed on your system.

1. Choose a password for the system's database user. Copy [.env.example](.env.example) to `.env` and set `DB_PASS` in it to a strong password. The `.env` file is ignored by Git.

```bash
cp .env.example .env
```

2. Build the environment. This will download any images and set up the custom images which are required to run in the next step.

```bash
docker compose build
```

3. Once the `build` has completed successfully you can then start the environment with the `up` command.

```bash
docker compose up
```

This may do some additional downloading which wasn't done during the `build` stage. This is normal.

The first time you run the `up` command the database will be initialised. An `address_book` database user is created with the password from your `.env` file, with access only to read and change data in the `address_book` database. The `root` user is given a random password which is shown in the console logs; the system doesn't need it.

4. Set the values in your `settings.local.inc.php` to match the Docker environment:

- `DB_SERVER` should be set to `mysql`.
- `DB_USER` should be set to `address_book`.
- `DB_PASS` should be set to the same password as `DB_PASS` in your `.env` file.
- `DB_NAME` should be set to `address_book`.
- `SITE_URL` should be set to the address from which the system will be accessible from. Typically http://localhost/ is acceptable.

### 2. Manual Installation

If you wish to set up the system manually then this too can be done.

#### Requirements

- A web server with PHP (8+ recommended).
- A relational database management system (RDBMS), such as MySQL or MariaDB.
- The `mysql` and `pdo` PHP modules should be installed and enabled for your version of PHP. For example if you are using PHP 8.5 then you would need to install `php8.5-mysql`.

#### Database Configuration

You should create a database called `address_book` along with a user which has permissions to this newly created database. The user only needs `SELECT`, `INSERT`, `UPDATE` and `DELETE`, so there's no need to use `root`. For example:

```sql
CREATE USER 'address_book'@'localhost' IDENTIFIED BY 'a-strong-password';
GRANT SELECT, INSERT, UPDATE, DELETE ON `address_book`.* TO 'address_book'@'localhost';
```

You should then import the [sql/sql.sql](sql/sql.sql) file into your database to set the system up to a baseline. For example:

```bash
mysql -u <username> (-p if your user account has a password) address_book < /location/to/sql/sql.sql
```

#### Settings Configuration

You should then set your `settings.local.inc.php` values to match your environment:

- `DB_SERVER` should be set to the IP address or hostname of your database server. If this is on the same server that the codebase is in then typically this would be `127.0.0.1`.
- `DB_USER` should be set to the user which you created for access to the database.
- `DB_PASS` should be set the password for the user which you created.
- `DB_NAME` should be set to `address_book`, if you used the default set up.
- `SITE_URL` should be the FQDN of the address of the server.

#### Web Server Configuration

You should configure your web server so that the document root is set as the [html](html) directory. However, the web server user for your configuration should have access to both the [html](html/) and [includes](includes/) directories.

### Testing

Once you have finished running one of the above methods you can then test if the system is working by visiting the address of the server in a browser.

If the system is working correctly then you should be prompted with a login window. The default credentials for the system are:

- Username: `admin`
- Password: `LetMeIn123`

You will be asked to choose a new password the first time you log in. Users added by another user, or whose password is reset by another user, are also asked to choose a new password when they next log in.

## Upgrading

### To 1.1.0

Version 1.1.0 changes the database structure. Existing installations must run the [sql/upgrades/1.1.0.sql](sql/upgrades/1.1.0.sql) file against their database before using the new version, for example:

```bash
mysql -u root -p address_book < sql/upgrades/1.1.0.sql
```

This also asks the default `admin` account to choose a new password if it still has the default password.

If you use Docker, `docker compose` now needs a `.env` file (see [Docker installation](#1-docker-recommended)). The `address_book` database user is only created automatically for a new database. For an existing database, create it yourself as the `root` user, using the same password as in `.env`:

```sql
CREATE USER 'address_book'@'%' IDENTIFIED BY 'your-db-pass';
GRANT SELECT, INSERT, UPDATE, DELETE ON `address_book`.* TO 'address_book'@'%';
```

Then change `DB_USER` and `DB_PASS` in your `settings.local.inc.php`.

## Local Settings Configuration Values

There are several configuration values which can be set in the `includes/settings.local.inc.php` file. Below lists settings with their appropriate values:

- `TIMEZONE` should be set to the timezone you require for the system. See the [PHP Manual](https://www.php.net/manual/en/timezones.php) for options. 
- `LOGIN_MAX_FAILED_USERNAME` (optional, default `5`) is the number of failed logins allowed for one username within the lockout window. Further attempts for that username are blocked until the window has passed.
- `LOGIN_MAX_FAILED_IP` (optional, default `20`) is the number of failed logins allowed from one IP address within the lockout window.
- `LOGIN_LOCKOUT_MINUTES` (optional, default `15`) is the length of the lockout window in minutes.

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
- `q` for the API query string - note that the query must contain no whitespace (including encoded whitespace characters).

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

- `findNumber` - Obtain the first contact found based on a queried phone number (mobile and home). Note that if more than one contact exists with the same phone number then this will only return the first result, based on the last name of the contacts in alphabetical order.
  - Example: a query of `api.php?m=findNumber&q=0987654321` (with the token in the `Authorization` header) will return the result (if it exists) for the phone number `0987654321`.

## License

This project is licensed under the [MIT License](LICENSE.md).

## Contributions

Pull requests (PRs) to this repository are welcome. If your PR is to address an open issue, please try to keep your changes specific to only that issue. Also please avoid addressing multiple issues within a single PR.
