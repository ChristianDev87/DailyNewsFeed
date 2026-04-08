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
            <td><?= htmlspecialchars(date('d.m.Y H:i', strtotime($cmd['created_at'])), ENT_QUOTES) ?></td>
            <td><?= $cmd['executed_at'] ? htmlspecialchars(date('d.m.Y H:i', strtotime($cmd['executed_at'])), ENT_QUOTES) : '—' ?></td>
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

<script>
function changePerPage(val) {
    const url = new URL(window.location.href);
    url.searchParams.set('per_page', val);
    url.searchParams.set('page', '1');
    window.location.href = url.toString();
}

async function botCmd(command) {
    const msgEl = document.getElementById('bot-msg');
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
</script>
