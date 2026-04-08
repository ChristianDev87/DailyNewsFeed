-- =============================================================================
-- Daily News — MySQL User anlegen
-- =============================================================================
-- WICHTIG: 'DEIN_PASSWORT_HIER' durch ein sicheres Passwort ersetzen!
-- WICHTIG: Host '%' erlaubt Verbindungen von überall — nur für Entwicklung.
--          In Produktion auf spezifische IPs einschränken:
--          CREATE USER 'daily_news_user'@'10.0.0.5' ...
--
-- Import: mysql -u root -p < database/create_user.sql
--
-- Berechtigungen: nur DML (SELECT, INSERT, UPDATE, DELETE).
-- Schema-Änderungen (CREATE, DROP, ALTER) immer über Root-User.
-- =============================================================================

CREATE USER IF NOT EXISTS 'daily_news_user'@'%'
    IDENTIFIED BY 'alawbzwy0607';

GRANT SELECT, INSERT, UPDATE, DELETE
    ON daily_news.*
    TO 'daily_news_user'@'%';

FLUSH PRIVILEGES;
