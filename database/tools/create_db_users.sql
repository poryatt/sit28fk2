-- Run once as a DB administrator. Adjust the host part ('localhost') to your server.
CREATE USER IF NOT EXISTS 'panel_app'@'localhost'      IDENTIFIED BY '<generate: 24+ chars>';
CREATE USER IF NOT EXISTS 'panel_migrator'@'localhost' IDENTIFIED BY '<generate: 24+ chars>';
GRANT SELECT, INSERT, UPDATE, DELETE ON business_panel.* TO 'panel_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX, REFERENCES ON business_panel.* TO 'panel_migrator'@'localhost';
FLUSH PRIVILEGES;
