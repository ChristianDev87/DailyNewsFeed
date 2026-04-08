#!/usr/bin/env python3
"""
Daily News Watchdog
Liest bot_commands aus der DB und führt systemd-Befehle aus.
"""

from __future__ import annotations

import os
import sys
import time
import subprocess
import logging
import json
from pathlib import Path
from datetime import datetime, timezone

import mysql.connector
from dotenv import load_dotenv

# .env aufwärts suchen
def find_env() -> Path | None:
    path = Path(__file__).resolve().parent
    while True:
        candidate = path / '.env'
        if candidate.exists():
            return candidate
        parent = path.parent
        if parent == path:
            return None
        path = parent

env_path = find_env()
if env_path:
    load_dotenv(env_path)

# Logging
class JsonFormatter(logging.Formatter):
    LEVEL_MAP = {
        logging.DEBUG:    'Debug',
        logging.INFO:     'Information',
        logging.WARNING:  'Warning',
        logging.ERROR:    'Error',
        logging.CRITICAL: 'Critical',
    }

    def format(self, record: logging.LogRecord) -> str:
        ts = datetime.fromtimestamp(record.created, tz=timezone.utc)
        level = self.LEVEL_MAP.get(record.levelno, 'Information')
        entry: dict = {
            '@t': ts.strftime('%Y-%m-%dT%H:%M:%S.') + f'{ts.microsecond // 1000:03d}Z',
            '@mt': record.getMessage(),
        }
        if level != 'Information':
            entry['@l'] = level
        if record.exc_info:
            entry['@x'] = self.formatException(record.exc_info)
        return json.dumps(entry, ensure_ascii=False)

_handler = logging.StreamHandler(sys.stdout)
_handler.setFormatter(JsonFormatter())
logging.root.setLevel(logging.INFO)
logging.root.handlers.clear()
logging.root.addHandler(_handler)
log = logging.getLogger('watchdog')

PROJ = os.environ.get('PROJECT_DIR', '/opt/daily-news')

# Bekannte Befehle und ihr Timeout in Sekunden
# Deploy-Befehle brauchen deutlich länger (git pull + docker build)
COMMANDS: dict[str, tuple[list[str], int]] = {
    'restart_bot':      (['docker', 'compose', '-f', f'{PROJ}/docker-compose.yml', 'restart', 'bot'],   30),
    'restart_watchdog': (['systemctl', 'restart', 'daily-news-watchdog'],                               30),
    'deploy_bot':       (['bash', f'{PROJ}/deploy-bot.sh'],                                            600),
    'deploy_frontend':  (['bash', f'{PROJ}/deploy-frontend.sh'],                                       600),
    'deploy_watchdog':  (['bash', f'{PROJ}/deploy-watchdog.sh'],                                       300),
}

POLL_INTERVAL = int(os.environ.get('WATCHDOG_INTERVAL', '10'))


def get_connection() -> mysql.connector.MySQLConnection:
    # WATCHDOG_DB_HOST/PORT überschreiben DB_HOST/PORT — nötig wenn DB im Docker läuft
    # (Watchdog ist auf dem Host und kann den Docker-internen Hostnamen 'db' nicht auflösen)
    return mysql.connector.connect(
        host=os.environ.get('WATCHDOG_DB_HOST', os.environ.get('DB_HOST', 'localhost')),
        port=int(os.environ.get('WATCHDOG_DB_PORT', os.environ.get('DB_PORT', '3306'))),
        database=os.environ.get('DB_NAME', 'daily_news'),
        user=os.environ['DB_USER'],
        password=os.environ['DB_PASS'],
        connection_timeout=10,
    )


def _mark_bot_offline(conn: mysql.connector.MySQLConnection) -> None:
    try:
        cursor = conn.cursor()
        cursor.execute("UPDATE bot_status SET status = 'offline' WHERE id = 1")
        conn.commit()
        cursor.close()
        log.info("Bot-Status auf offline gesetzt")
    except Exception as exc:
        log.warning(f"Fehler beim Setzen des Offline-Status: {exc}")


def process_pending(conn: mysql.connector.MySQLConnection) -> None:
    cursor = conn.cursor(dictionary=True)
    cursor.execute(
        "SELECT id, command FROM bot_commands "
        "WHERE status = 'pending' ORDER BY created_at ASC"
    )
    rows = cursor.fetchall()

    for row in rows:
        cmd_id  = row['id']
        command = row['command']

        if command not in COMMANDS:
            log.warning(f"Unbekannter Befehl '{command}' (id={cmd_id}) — übersprungen")
            cursor.execute(
                "UPDATE bot_commands SET status='failed', executed_at=NOW() WHERE id=%s",
                (cmd_id,)
            )
            conn.commit()
            continue

        cmd_args, timeout = COMMANDS[command]
        log.info(f"Führe aus: '{command}' (id={cmd_id}, timeout={timeout}s)")
        try:
            result = subprocess.run(
                cmd_args,
                capture_output=True,
                text=True,
                timeout=timeout,
            )
            if result.returncode == 0:
                status = 'done'
                log.info(f"'{command}' erfolgreich abgeschlossen (id={cmd_id})")
                if command == 'stop_bot':
                    _mark_bot_offline(conn)
            else:
                status = 'failed'
                log.error(
                    f"'{command}' fehlgeschlagen (exit={result.returncode}): "
                    f"{result.stderr.strip()} (id={cmd_id})"
                )
        except subprocess.TimeoutExpired:
            status = 'failed'
            log.error(f"'{command}' Timeout nach 30s (id={cmd_id})")
        except Exception as exc:
            status = 'failed'
            log.error(f"'{command}' Ausnahme: {exc} (id={cmd_id})")

        cursor.execute(
            "UPDATE bot_commands SET status=%s, executed_at=NOW() WHERE id=%s",
            (status, cmd_id)
        )
        conn.commit()

    cursor.close()


def main() -> None:
    log.info(f"Watchdog gestartet (Intervall: {POLL_INTERVAL}s)")

    while True:
        try:
            conn = get_connection()
            process_pending(conn)
            conn.close()
        except mysql.connector.Error as exc:
            log.error(f"Datenbankfehler: {exc}")
        except KeyError as exc:
            log.critical(f"Pflicht-Umgebungsvariable fehlt: {exc}")
            sys.exit(1)
        except Exception as exc:
            log.error(f"Unerwarteter Fehler: {exc}")

        time.sleep(POLL_INTERVAL)


if __name__ == '__main__':
    main()
