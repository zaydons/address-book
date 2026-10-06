/*
Give the system's database user (created from MYSQL_USER in docker-compose.yml) only the access it needs:
reading and changing data in the address_book database, but not changing its structure or accessing other databases.
*/
GRANT SELECT, INSERT, UPDATE, DELETE ON `address_book`.* TO 'address_book'@'%';
