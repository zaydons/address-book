-- Sample data for testing tools/mysql-to-sqlite.php, loaded after mysql-1.0.6.sql
-- The user jsmith's password is Custom1234x
USE address_book;
INSERT INTO users (user_id, username, hashed_password, full_name) VALUES ('uSeR0000001a', 'jsmith', '$2y$10$xibnz3of3mlPnLgchyl8FuBdvDO72bVJljG9VKqt.DJN.b1twOyWS', 'Jo Smith');
INSERT INTO contacts (contact_id, first_name, middle_name, last_name, contact_number_home, contact_number_mobile, contact_email, date_of_birth, address_line_1, address_line_2, address_town, address_county, address_post_code) VALUES
('aB3dE5fG7hJ9', 'William', NULL, 'Shakespeare', '01789204016', '07700900123', 'will@example.com', '1964-04-23', 'Henley Street', NULL, 'Stratford-upon-Avon', 'Warwickshire', 'CV37 6QW'),
('kM2nP4qR6sT8', 'Zoë', '', 'Brontë', NULL, NULL, NULL, NULL, 'Haworth Parsonage', NULL, 'Keighley', 'West Yorkshire', 'BD22 8DR');
INSERT INTO api (api_id, ip, cosmetic_name) VALUES ('Pz7Kq2Lm9Xa4', NULL, 'Phone system');
INSERT INTO logs (datetime, action, url, user, ip, user_agent) VALUES ('2024-01-02 03:04:05', 'Login Success', 'http://x/login.php', 'Jo Smith [jsmith]', '10.0.0.5', 'Firefox');
