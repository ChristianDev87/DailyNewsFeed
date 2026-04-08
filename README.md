# DailyNewsFeed

> Selbst gehostetes Discord-Nachrichtensystem — RSS-Feeds automatisch als Digest in Discord-Kanäle senden.

*Irgendwann sagt man Ja — entstanden aus den Wünschen guter Freunde.* 🙌

---

## Komponenten

| Komponente | Technologie | Beschreibung |
|---|---|---|
| **Bot** | .NET 9 | Discord-Bot mit Scheduler, Slash-Commands und RSS-Verarbeitung |
| **Frontend** | PHP 8.2 + Slim 4 | Web-Interface für Discord OAuth2 Login, Kanal- und Feed-Verwaltung |
| **Watchdog** | Python 3 | systemd-Service auf dem Host — verarbeitet Bot-Befehle (Restart, Deploy) |

Frontend und Bot laufen als Docker-Container. Der Watchdog läuft als systemd-Service direkt auf dem Host.

---

## Voraussetzungen

- **Docker** + **Docker Compose**
- **MariaDB 10.6+** oder **MySQL 8+** — extern (Option A) oder als Docker-Container (Option B)
- **Discord-Bot-Account** mit aktivierten Gateway Intents
- **Discord OAuth2 App** für das Web-Frontend
- **Reverse Proxy** auf dem Host (Apache2 oder Nginx) für Port 80/443

---

## Schnellstart

### 1. Repository klonen

```bash
git clone https://github.com/ChristianDev87/DailyNewsFeed.git /opt/daily-news
cd /opt/daily-news
```

### 2. Konfiguration erstellen

```bash
cp .env.example .env
nano .env
```

Alle Pflichtfelder ausfüllen — siehe [Konfiguration](#konfiguration).

### 3. Datenbank einrichten

**Option A — Externe MariaDB** (eigene Datenbank vorhanden):

```bash
mysql -u root -p < database/schema.sql
mysql -u root -p < database/create_user.sql
```

In `.env`:
```
DB_HOST=dein-datenbankserver
DB_PORT=3306
```

**Option B — MariaDB im Docker** (keine eigene Datenbank):

Schema wird beim ersten Start automatisch eingespielt. In `.env` eintragen:
```
DB_HOST=db               # Docker-interner Hostname
DB_PORT=3306
DB_ROOT_PASS=sicheres-passwort
WATCHDOG_DB_HOST=127.0.0.1   # Watchdog läuft auf dem Host
WATCHDOG_DB_PORT=3307        # Container ist auf Host-Port 3307 erreichbar
```

### 4. Log-Verzeichnisse anlegen

```bash
mkdir -p /var/log/dailynews/{bot,watchdog,frontend}
```

### 5. Container starten

**Option A:**
```bash
docker compose up -d
```

**Option B** (mit DB-Container):
```bash
docker compose --profile with-db up -d
```

### 6. Watchdog einrichten

```bash
cd /opt/daily-news/watchdog
python3 -m venv venv
venv/bin/pip install -r requirements.txt
cp daily-news-watchdog.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now daily-news-watchdog
```

### 7. Reverse Proxy konfigurieren

**Apache2:**
```apache
<VirtualHost *:80>
    ServerName daily-news.example.com
    ProxyPreserveHost On
    ProxyPass        / http://127.0.0.1:8080/
    ProxyPassReverse / http://127.0.0.1:8080/
</VirtualHost>
```

**Nginx:**
```nginx
server {
    listen 80;
    server_name daily-news.example.com;
    location / {
        proxy_pass         http://127.0.0.1:8080;
        proxy_set_header   Host $host;
        proxy_set_header   X-Real-IP $remote_addr;
    }
}
```

SSL über Certbot: `certbot --apache` oder `certbot --nginx`

---

## Konfiguration

Alle Variablen in `.env` (Vorlage: `.env.example`):

| Variable | Pflicht | Beschreibung |
|---|---|---|
| `DB_HOST` | ✅ | Datenbank-Host — Option A: z.B. `localhost`, Option B: `db` |
| `DB_PORT` | ✅ | Datenbank-Port (Standard: `3306`) |
| `DB_NAME` | ✅ | Datenbankname |
| `DB_USER` | ✅ | Datenbankbenutzer |
| `DB_PASS` | ✅ | Datenbankpasswort |
| `DB_ROOT_PASS` | Option B | Root-Passwort für den MariaDB-Container |
| `WATCHDOG_DB_HOST` | Option B | DB-Host für den Watchdog (`127.0.0.1`) — überschreibt `DB_HOST` |
| `WATCHDOG_DB_PORT` | Option B | `3307` — Container ist auf Host-Port 3307 erreichbar (kein Konflikt mit vorhandenem MySQL) |
| `DISCORD_TOKEN` | ✅ | Bot-Token aus dem Discord Developer Portal |
| `TOKEN_ENCRYPTION_KEY` | ✅ | AES-256 Schlüssel (min. 32 Zeichen) — für Custom Bot Tokens |
| `DISCORD_CLIENT_ID` | ✅ | OAuth2 Client ID (Frontend-Login) |
| `DISCORD_CLIENT_SECRET` | ✅ | OAuth2 Client Secret |
| `DISCORD_REDIRECT_URI` | ✅ | OAuth2 Redirect URL (z.B. `https://example.com/auth/callback`) |
| `APP_URL` | ✅ | Öffentliche URL des Frontends |
| `APP_SECRET` | ✅ | Session-Secret (min. 32 Zeichen, zufällig) |
| `LOG_LEVEL` | — | Log-Level (Standard: `Information`) |

Schlüssel generieren:
```bash
openssl rand -base64 32
```

---

## Discord-Bot einrichten

1. [discord.com/developers/applications](https://discord.com/developers/applications) → Neue Applikation
2. **Bot** → Token kopieren → `DISCORD_TOKEN`
3. **Privileged Gateway Intents**: `Server Members Intent` + `Message Content Intent` aktivieren
4. **OAuth2** → Redirect URL eintragen → `DISCORD_CLIENT_ID` + `DISCORD_CLIENT_SECRET` kopieren
5. Bot einladen: **OAuth2 → URL Generator** → Scopes: `bot`, `applications.commands` → Berechtigungen: `Send Messages`, `Read Message History`, `Create Public Threads`

---

## Slash-Commands

Alle Befehle beginnen mit `/dnews`:

| Befehl | Beschreibung |
|---|---|
| `/dnews setup` | Kanal für den Bot registrieren |
| `/dnews senden` | Digest sofort auslösen |
| `/dnews status` | Aktuellen Status anzeigen |
| `/dnews feeds` | Konfigurierte Feeds anzeigen |
| `/dnews pause` | Automatischen Digest pausieren |
| `/dnews fortsetzen` | Digest fortsetzen |

---

## Architektur

```
Host:
├── Apache2 / Nginx     — Reverse Proxy (Port 80/443)
├── Watchdog            — systemd-Service (Python)
│   └── liest bot_commands aus DB → führt Deploy-Scripts aus
└── Docker:
    ├── bot             — .NET 9 Discord-Bot
    │   └── Scheduler (alle 4h) + Slash-Commands
    └── frontend        — PHP-FPM + Nginx
        └── Discord OAuth2 Login + Feed-Verwaltung

Logs: /var/log/dailynews/
├── bot/                — CLEF/JSON (täglich rollend, 14 Tage)
├── watchdog/           — plaintext
└── frontend/           — Nginx Access/Error Logs
```

**Kommunikationsfluss:**
- Frontend → `bot_commands`-Tabelle → Watchdog → Docker/systemd
- Bot ↔ Discord Gateway (ausgehend, kein eingehender Port nötig)
- Alle Komponenten teilen eine externe MariaDB

---

## Deployment (Updates)

```bash
# Bot aktualisieren
bash /root/deploy-bot.sh

# Frontend aktualisieren
bash /root/deploy-frontend.sh
```

Oder direkt über das Admin-Panel im Frontend (kein SSH nötig).

---

## Tech-Stack

| Komponente | Stack |
|---|---|
| Bot | .NET 9, Discord.Net, MySqlConnector, Dapper, Serilog, CodeHollow.FeedReader |
| Frontend | PHP 8.2, Slim 4, Nginx (Alpine), Composer |
| Watchdog | Python 3, systemd |
| Datenbank | MariaDB 10.6+ / MySQL 8+ |
| Container | Docker, Docker Compose |

---

## Lizenz

MIT
