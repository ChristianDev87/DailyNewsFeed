<?php
/**
 * @var array  $stats
 * @var array  $commands
 * @var string $csrfToken
 * @var int    $page
 * @var int    $perPage
 * @var int    $totalPages
 * @var int    $totalCmds
 */
$fmtBerlin = static fn (?string $ts): string => $ts
    ? (new \DateTime($ts, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i')
    : '—';
?>
<h1>Admin</h1>

<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['active_channels'] ?? 0) ?></div>
        <div class="stat-label">Aktive Kanäle</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['active_guilds'] ?? 0) ?></div>
        <div class="stat-label">Aktive Server</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['articles_today'] ?? 0) ?></div>
        <div class="stat-label">Artikel heute</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= (int)($stats['pending_commands'] ?? 0) ?></div>
        <div class="stat-label">Ausstehende Befehle</div>
    </div>
</div>

<div class="bot-panel">
    <h2>Bot-Verwaltung</h2>
    <div class="actions">
        <button class="btn btn-primary" onclick="botCmd('run_digest')">▶ Digest ausführen</button>
        <button class="btn btn-ghost"   onclick="botCmd('restart_bot')">🔄 Bot neu starten</button>
        <button class="btn btn-danger"  onclick="botCmd('stop_bot')">⏹ Bot stoppen</button>
    </div>
    <p id="bot-msg" style="margin-top:10px;font-size:14px"></p>
</div>

<div class="bot-panel" style="margin-top:24px">
    <h2>Deployment</h2>
    <div class="actions">
        <button class="btn btn-primary deploy-btn" data-cmd="deploy_bot"      onclick="deployCmd('deploy_bot')">🚀 Bot deployen</button>
        <button class="btn btn-primary deploy-btn" data-cmd="deploy_frontend" onclick="deployCmd('deploy_frontend')">🚀 Frontend deployen</button>
    </div>
    <p style="font-size:13px;color:var(--muted);margin-top:8px">
        Führt git pull + docker build + docker up auf dem Server aus (~1–2 Min). Watchdog-Redeploy: SSH nötig.
    </p>
    <p id="deploy-msg" style="margin-top:10px;font-size:14px"></p>
</div>

<div style="display:flex;align-items:baseline;gap:12px;margin-top:32px">
    <h2 style="margin:0">Befehls-Historie</h2>
    <span style="color:var(--muted);font-size:13px"><?= $totalCmds ?> Einträge</span>
</div>

<?php if (empty($commands)): ?>
    <p style="color:var(--muted)">Noch keine Befehle.</p>
<?php else: ?>
<div class="table-wrap">
<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Befehl</th>
            <th>Von</th>
            <th>Status</th>
            <th>Erstellt</th>
            <th>Ausgeführt</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($commands as $cmd): ?>
        <tr>
            <td><?= (int)$cmd['id'] ?></td>
            <td><code><?= htmlspecialchars($cmd['command'], ENT_QUOTES) ?></code></td>
            <td><?= $cmd['created_by'] === 'scheduler' ? '🕐 Scheduler' : '👤 Admin' ?></td>
            <td>
                <span class="status-badge status-<?= htmlspecialchars($cmd['status'], ENT_QUOTES) ?>">
                    <?= match($cmd['status']) {
                        'done'    => '✅ done',
                        'pending' => '⏳ pending',
                        'failed'  => '❌ failed',
                        default   => htmlspecialchars($cmd['status'], ENT_QUOTES),
                    } ?>
                </span>
            </td>
            <td><?= htmlspecialchars($fmtBerlin($cmd['created_at']), ENT_QUOTES) ?></td>
            <td><?= htmlspecialchars($fmtBerlin($cmd['executed_at']), ENT_QUOTES) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<div class="pagination">
    <div class="pagination-left">
        <label style="font-size:13px;color:var(--muted)">Einträge pro Seite:</label>
        <select class="per-page-select" onchange="changePerPage(this.value)">
            <?php foreach ([5, 10, 15, 20, 50, 100] as $opt): ?>
                <option value="<?= $opt ?>" <?= $opt === $perPage ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="pagination-right">
        <?php if ($page > 1): ?>
            <a href="/admin?page=<?= $page - 1 ?>&per_page=<?= $perPage ?>" class="btn btn-ghost btn-sm">← Zurück</a>
        <?php endif; ?>
        <span class="page-info">Seite <?= $page ?> / <?= $totalPages ?></span>
        <?php if ($page < $totalPages): ?>
            <a href="/admin?page=<?= $page + 1 ?>&per_page=<?= $perPage ?>" class="btn btn-ghost btn-sm">Weiter →</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<style>
.log-tab.active { background: var(--accent, #5865f2); color: #fff; }
</style>

<div class="bot-panel" style="margin-top:32px">
    <div style="display:flex;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:16px">
        <h2 style="margin:0">Logs</h2>
        <div style="display:flex;gap:4px">
            <button class="log-tab btn btn-ghost btn-sm active" data-source="bot" data-file="">Bot</button>
            <button class="log-tab btn btn-ghost btn-sm" data-source="watchdog" data-file="">Watchdog</button>
            <button class="log-tab btn btn-ghost btn-sm" data-source="frontend" data-file="access">Nginx Access</button>
            <button class="log-tab btn btn-ghost btn-sm" data-source="frontend" data-file="error">Nginx Error</button>
        </div>
        <div style="margin-left:auto;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <select id="log-lines" class="per-page-select">
                <?php foreach ([50, 100, 200, 500] as $n): ?>
                    <option value="<?= $n ?>" <?= $n === 200 ? 'selected' : '' ?>><?= $n ?> Zeilen</option>
                <?php endforeach; ?>
            </select>
            <label style="font-size:13px;color:var(--muted);display:flex;align-items:center;gap:6px;cursor:pointer">
                <input type="checkbox" id="log-live"> Live
            </label>
            <button class="btn btn-ghost btn-sm" onclick="loadLogs()">↻ Aktualisieren</button>
        </div>
    </div>
    <div id="log-container" class="table-wrap" style="max-height:420px;overflow-y:auto;margin-top:0">
        <table class="data-table">
            <thead>
                <tr><th style="white-space:nowrap">Zeitpunkt</th><th>Level</th><th style="width:100%">Nachricht</th></tr>
            </thead>
            <tbody id="log-body">
                <tr><td colspan="3" style="color:var(--muted)">Klicke Aktualisieren…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    let _source    = 'bot';
    let _file      = '';
    let _liveTimer = null;

    document.querySelectorAll('.log-tab').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.log-tab').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            _source = btn.dataset.source;
            _file   = btn.dataset.file ?? '';
            loadLogs();
        });
    });

    document.getElementById('log-live').addEventListener('change', function () {
        if (this.checked) {
            loadLogs();
            _liveTimer = setInterval(loadLogs, 5000);
        } else {
            clearInterval(_liveTimer);
            _liveTimer = null;
        }
    });

    window.loadLogs = async function () {
        const lines = document.getElementById('log-lines').value;
        const url   = `/api/admin/logs?source=${encodeURIComponent(_source)}&lines=${encodeURIComponent(lines)}&file=${encodeURIComponent(_file)}`;
        const tbody = document.getElementById('log-body');
        try {
            const res  = await fetch(url);
            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                tbody.innerHTML = `<tr><td colspan="3" style="color:var(--danger)">Fehler ${res.status}: ${esc(err.error ?? res.statusText)}</td></tr>`;
                return;
            }
            const data = await res.json();
            if (!data.lines || data.lines.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="color:var(--muted)">Keine Einträge.</td></tr>';
                return;
            }
            tbody.innerHTML = data.lines.map(l => l.raw !== undefined
                ? `<tr><td colspan="3"><code style="font-size:12px;word-break:break-all">${esc(l.raw)}</code></td></tr>`
                : `<tr>
                    <td style="white-space:nowrap;font-size:12px">${esc(fmtTs(l.timestamp))}</td>
                    <td><span class="status-badge ${lvlClass(l.level)}">${esc(l.level)}</span></td>
                    <td style="font-size:13px;word-break:break-word">${esc(l.message)}${l.exception ? `<br><span style="color:var(--danger);font-size:11px">${esc(l.exception)}</span>` : ''}</td>
                   </tr>`
            ).join('');
            const c = document.getElementById('log-container');
            c.scrollTop = c.scrollHeight;
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="3" style="color:var(--danger)">Fehler: ${esc(e.message)}</td></tr>`;
        }
    };

    function lvlClass(level) {
        const l = (level ?? '').toLowerCase();
        if (l === 'error' || l === 'critical') return 'status-failed';
        if (l === 'warning') return 'status-pending';
        return 'status-done';
    }

    function fmtTs(ts) {
        if (!ts) return '—';
        try { return new Date(ts).toLocaleString('de-DE', { timeZone: 'Europe/Berlin' }); }
        catch { return ts; }
    }

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}());
</script>

<script>
function changePerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', val);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

async function botCmd(command, msgId = 'bot-msg') {
    const msgEl = document.getElementById(msgId);
    msgEl.textContent = 'Sende…';
    const res  = await fetch('/api/bot/command', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>' },
        body: JSON.stringify({ command }),
    });
    const data = await res.json();
    msgEl.textContent = data.message ?? (data.success ? 'Befehl gesendet.' : `Fehler: ${data.error}`);
    if (res.ok) setTimeout(() => location.reload(), 2000);
}

async function deployCmd(command) {
    const deployBtns = document.querySelectorAll('.deploy-btn');
    const clickedBtn = document.querySelector(`.deploy-btn[data-cmd="${command}"]`);
    const msgEl      = document.getElementById('deploy-msg');
    const origText   = clickedBtn.textContent;

    deployBtns.forEach(b => { b.disabled = true; });
    clickedBtn.textContent = '⏳ läuft…';
    msgEl.textContent = '';

    // Send the command
    let data;
    try {
        const res = await fetch('/api/bot/command', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>' },
            body:    JSON.stringify({ command }),
        });
        data = await res.json();
        if (!res.ok || !data.success) {
            msgEl.textContent = `❌ ${data.error ?? 'Befehl fehlgeschlagen'}`;
            deployBtns.forEach(b => { b.disabled = false; });
            clickedBtn.textContent = origText;
            return;
        }
    } catch (e) {
        msgEl.textContent = `❌ Netzwerkfehler: ${e.message}`;
        deployBtns.forEach(b => { b.disabled = false; });
        clickedBtn.textContent = origText;
        return;
    }

    const cmdId = data.cmdId;
    if (!cmdId) {
        msgEl.textContent = '❌ Kein Command-ID erhalten. Prüfe die Logs.';
        deployBtns.forEach(b => { b.disabled = false; });
        clickedBtn.textContent = origText;
        return;
    }
    const started  = Date.now();
    const maxMs    = 10 * 60 * 1000; // 10 minutes
    let consecutive = 0;
    let tickerInterval, pollInterval, timeoutHandle;

    tickerInterval = setInterval(() => {
        const elapsed = Math.floor((Date.now() - started) / 1000);
        const mins    = Math.floor(elapsed / 60);
        const secs    = String(elapsed % 60).padStart(2, '0');
        msgEl.textContent = `⏳ Deploy läuft… ${mins}:${secs} min`;
    }, 1000);

    function finish(msg) {
        clearInterval(tickerInterval);
        clearInterval(pollInterval);
        clearTimeout(timeoutHandle);
        deployBtns.forEach(b => { b.disabled = false; });
        clickedBtn.textContent = origText;
        msgEl.textContent = msg;
    }

    timeoutHandle = setTimeout(() => finish('⚠️ Deploy dauert ungewöhnlich lange. Prüfe die Logs.'), maxMs);

    pollInterval = setInterval(async () => {
        try {
            const res = await fetch(`/api/bot/command/${cmdId}/status`);
            if (!res.ok) {
                consecutive++;
                if (consecutive >= 10) finish('⚠️ Server nicht erreichbar. Prüfe die Logs.');
                return;
            }
            const d = await res.json();
            consecutive = 0;
            if (d.status === 'done') {
                finish('✅ Deploy abgeschlossen. Seite wird neu geladen…');
                setTimeout(() => location.reload(), 1500);
            } else if (d.status === 'failed') {
                finish('❌ Deploy fehlgeschlagen. Prüfe die Logs.');
            }
            // 'pending' and 'in_progress' → keep polling
        } catch (_) {
            consecutive++;
            if (consecutive >= 10) finish('⚠️ Server nicht erreichbar. Prüfe die Logs.');
        }
    }, 3000);
}
</script>
