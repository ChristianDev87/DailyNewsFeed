-- =============================================================================
-- Daily News — MySQL Schema
-- =============================================================================
-- Voraussetzungen: MySQL 5.7+ oder MariaDB 10.3+
-- Import: mysql -u root -p < database/schema.sql
--
-- Sicher für Mehrfach-Import: CREATE TABLE IF NOT EXISTS, kein DROP TABLE.
-- Zum Reset: Tabellen manuell droppen, dann neu importieren.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS daily_news
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE daily_news;

-- -----------------------------------------------------------------------------
-- channels
-- Registrierte Discord-Kanäle. Ein Kanal pro Eintrag.
-- Optional: eigener Bot-Token (AES-256 verschlüsselt) damit Nachrichten
-- unter dem eigenen Bot-Namen des Servers erscheinen.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS channels (
    channel_id                  VARCHAR(32)  NOT NULL,
    guild_id                    VARCHAR(32)  NOT NULL,
    guild_name                  VARCHAR(255),
    channel_name                VARCHAR(255),
    owner_user_id               VARCHAR(32)  NOT NULL,
    active                      TINYINT(1)   NOT NULL DEFAULT 1,
    custom_bot_token_encrypted  TEXT         NULL,       -- AES-256 verschlüsselt
    custom_bot_token_iv         VARCHAR(64)  NULL,       -- IV für AES-256-CBC
    created_at                  DATETIME     NOT NULL,
    PRIMARY KEY (channel_id),
    INDEX idx_guild  (guild_id),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- channel_categories
-- Kategorien pro Kanal (z.B. "🤖 KI & Künstliche Intelligenz").
-- use_default=1: Standard-Feeds dieser Kategorie aus RSS_CATEGORIES nutzen.
-- Kein Eintrag in channels → Fallback auf globale RSS_CATEGORIES aus Script.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS channel_categories (
    id          INT          NOT NULL AUTO_INCREMENT,
    channel_id  VARCHAR(32)  NOT NULL,
    label       VARCHAR(255) NOT NULL,
    emoji       VARCHAR(10)  NOT NULL,
    position    INT          NOT NULL DEFAULT 0,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    use_default TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    FOREIGN KEY (channel_id)
        REFERENCES channels(channel_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- channel_feeds
-- RSS/Atom-Feeds pro Kategorie. max_items begrenzt Artikel pro Lauf.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS channel_feeds (
    id          INT          NOT NULL AUTO_INCREMENT,
    category_id INT          NOT NULL,
    name        VARCHAR(255) NOT NULL,
    url         TEXT         NOT NULL,
    max_items   INT          NOT NULL DEFAULT 5,
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    FOREIGN KEY (category_id)
        REFERENCES channel_categories(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- seen_articles
-- Duplikat-Schutz: ein Artikel (url_hash) pro Kanal wird nur einmal gesendet.
-- Verhindert Duplikate unabhängig davon ob Bot-Scheduler oder Cronjob ausgelöst.
-- Migriert aus SQLite (news_digest.db).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS seen_articles (
    url_hash   VARCHAR(64)  NOT NULL,
    channel_id VARCHAR(32)  NOT NULL,
    url        TEXT         NOT NULL,
    title      TEXT,
    source     VARCHAR(255),
    seen_at    DATETIME     NOT NULL,
    PRIMARY KEY (url_hash, channel_id),
    INDEX idx_seen_at (seen_at),
    INDEX idx_channel (channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- daily_threads
-- Discord Thread-ID pro Tag und Kanal.
-- Erster Lauf des Tages erstellt Thread, alle weiteren nutzen ihn.
-- Migriert aus SQLite (news_digest.db).
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS daily_threads (
    date        DATE         NOT NULL,
    channel_id  VARCHAR(32)  NOT NULL,
    thread_id   VARCHAR(32)  NOT NULL,
    created_at  DATETIME     NOT NULL,
    PRIMARY KEY (date, channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- sessions
-- OAuth2-Sessions für das PHP Web-Interface.
-- Zwei-Ebenen-Cache: HttpOnly Cookie → PHP $_SESSION (RAM) → diese Tabelle.
-- DB-Call nur beim ersten Request nach Login und bei Token-Erneuerung (~alle 6 Tage).
-- Sliding Session: session_expires_at wird bei jedem Request um 7 Tage verlängert.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
    session_token            VARCHAR(128) NOT NULL,
    discord_user_id          VARCHAR(32)  NOT NULL,
    discord_username         VARCHAR(255),
    access_token             TEXT         NOT NULL,
    access_token_expires_at  DATETIME     NOT NULL,  -- Discord Access Token: 7 Tage
    refresh_token            TEXT         NOT NULL,
    refresh_token_expires_at DATETIME     NOT NULL,  -- Discord Refresh Token: 30 Tage
    session_expires_at       DATETIME     NOT NULL,  -- Inaktivitäts-Timeout: 7 Tage
    created_at               DATETIME     NOT NULL,
    PRIMARY KEY (session_token),
    INDEX idx_session_expires (session_expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- bot_commands
-- Kommunikationsbrücke: PHP schreibt Befehle, Watchdog liest und führt aus.
-- Erlaubte Werte für command: 'restart_bot', 'restart_watchdog'
-- Erlaubte Werte für status:  'pending', 'done', 'failed'
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bot_commands (
    id          INT         NOT NULL AUTO_INCREMENT,
    command     VARCHAR(50) NOT NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'pending',
    created_by  VARCHAR(32),                           -- Discord User ID
    created_at  DATETIME    NOT NULL,
    executed_at DATETIME    NULL,
    PRIMARY KEY (id),
    INDEX idx_status  (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- known_guilds
-- Bekannte Discord-Server. Wird beim Bot-Start und bei Guild-Events synchronisiert.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS known_guilds (
    guild_id    VARCHAR(32)  NOT NULL,
    guild_name  VARCHAR(255),
    active      TINYINT(1)   NOT NULL DEFAULT 1,
    joined_at   DATETIME     NOT NULL,
    updated_at  DATETIME     NOT NULL,
    PRIMARY KEY (guild_id),
    INDEX idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- bot_status
-- Heartbeat: der Bot schreibt alle N Sekunden seinen Status.
-- Immer genau eine Zeile (id=1) — INSERT ... ON DUPLICATE KEY UPDATE.
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bot_status (
    id          INT          NOT NULL,
    last_seen   DATETIME     NOT NULL,
    status      VARCHAR(20)  NOT NULL DEFAULT 'offline',
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialzeile für bot_status (id=1 muss existieren)
INSERT IGNORE INTO bot_status (id, last_seen, status)
VALUES (1, UTC_TIMESTAMP(), 'offline');
