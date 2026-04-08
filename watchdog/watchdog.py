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
from pathlib import Path

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
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s [%(levelname)s] %(message)s',
    handlers=[logging.StreamHandler(sys.stdout)],
)
log = logging.getLogger('watchdog')

# Bekannte Befehle → systemd-Unit-Aktionen
COMMANDS: dict[str, list[str]] = {
    'restart_bot': ['systemctl', 'restart', 'daily-news-bot'],
    'run_digest':  ['systemctl', 'restart', 'daily-news-bot'],
    'stop_bot':    ['systemctl', 'stop',    'daily-news-bot'],
}

POLL_INTERVAL = int(os.environ.get('WATCHDOG_INTERVAL', '10'))


def get_connection() -> mysql.connector.MySQLConnection:
    return mysql.connector.connect(
        host=os.environ.get('DB_HOST', 'localhost'),
        port=int(os.environ.get('DB_PORT', '3306')),
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

        log.info(f"Führe aus: '{command}' (id={cmd_id})")
        try:
            result = subprocess.run(
                COMMANDS[command],
                capture_output=True,
                text=True,
                timeout=30,
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
