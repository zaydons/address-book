-- Address Book database structure (SQLite)
--
-- The system runs this automatically the first time it opens a new database file, so it doesn't need to be run by hand.
-- Every statement is safe to run more than once.
-- Text lengths aren't enforced by SQLite; the system checks them when forms are submitted.

-- API tokens
CREATE TABLE IF NOT EXISTS api (
  api_id TEXT NOT NULL PRIMARY KEY, -- Token used for API call
  ip TEXT DEFAULT NULL, -- IP address from which API call is authorised
  cosmetic_name TEXT -- Cosmetic description of the API token
);

-- Contacts in the address book
CREATE TABLE IF NOT EXISTS contacts (
  contact_id TEXT NOT NULL PRIMARY KEY,
  first_name TEXT DEFAULT NULL,
  middle_name TEXT DEFAULT NULL,
  last_name TEXT DEFAULT NULL,
  contact_number_home TEXT DEFAULT NULL,
  contact_number_mobile TEXT DEFAULT NULL,
  contact_email TEXT DEFAULT NULL,
  date_of_birth TEXT DEFAULT NULL, -- YYYY-MM-DD
  address_line_1 TEXT DEFAULT NULL,
  address_line_2 TEXT DEFAULT NULL,
  address_town TEXT DEFAULT NULL,
  address_county TEXT DEFAULT NULL,
  address_post_code TEXT DEFAULT NULL
);

-- Log of actions taken in the system
CREATE TABLE IF NOT EXISTS logs (
  log_id INTEGER PRIMARY KEY AUTOINCREMENT,
  datetime TEXT DEFAULT NULL, -- YYYY-MM-DD HH:MM:SS
  action TEXT,
  url TEXT DEFAULT NULL,
  user TEXT DEFAULT NULL,
  ip TEXT DEFAULT NULL,
  user_agent TEXT DEFAULT NULL
);

-- Speeds up counting recent failed logins from an IP address
CREATE INDEX IF NOT EXISTS ip_datetime ON logs (ip, datetime);

-- Users who can log in to the system
CREATE TABLE IF NOT EXISTS users (
  user_id TEXT NOT NULL PRIMARY KEY,
  username TEXT DEFAULT NULL UNIQUE COLLATE NOCASE, -- Usernames aren't case sensitive, so Admin and admin are the same user
  hashed_password TEXT DEFAULT NULL,
  full_name TEXT DEFAULT NULL,
  must_change_password INTEGER NOT NULL DEFAULT 0 -- User must choose a new password at next login
);

-- The default admin user (password LetMeIn123), who must choose a new password at first login
INSERT OR IGNORE INTO users (user_id, username, hashed_password, full_name, must_change_password) VALUES
('PB0gY2TZKYTc', 'admin', '$2y$10$Mjg4OGQ1NzdmNWY2ZGJiO.5O1IjWagPSmROXjw9h1IWz3JYyr5Iu.', 'Admin User', 1);
