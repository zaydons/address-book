/*
Upgrade an existing Address Book database to version 1.1.0.
New installations do not need this file, as sql/sql.sql already includes these changes.

Run against your database, for example:
mysql -u <username> -p address_book < sql/upgrades/1.1.0.sql
*/

USE `address_book`;

/* Allow room for longer password hashes from newer algorithms */
ALTER TABLE `users` MODIFY `hashed_password` varchar(255) DEFAULT NULL;

/* Users who have been given a password by someone else must choose a new one at next login */
ALTER TABLE `users` ADD COLUMN `must_change_password` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'User must choose a new password at next login';

/* Force a password change for the default admin account if it still has the default password */
UPDATE `users` SET `must_change_password` = 1
WHERE `user_id` = 'PB0gY2TZKYTc'
AND `hashed_password` = '$2y$10$Mjg4OGQ1NzdmNWY2ZGJiO.5O1IjWagPSmROXjw9h1IWz3JYyr5Iu.';

/* Speed up counting recent failed logins from an IP address */
ALTER TABLE `logs` ADD KEY `ip_datetime` (`ip`,`datetime`);
