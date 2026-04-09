<?php
/**
 * @var array  $channels
 * @var bool   $isSuperAdmin
 * @var string $csrfToken
 * @var string $inviteUrl
 */
$fmtBerlin = static fn (?string $ts): string => $ts
    ? (new \DateTime($ts, new \DateTimeZone('UTC')))->setTimezone(new \DateTimeZone('Europe/Berlin'))->format('d.m.Y H:i')
    : '';
?>
<h1>Dashboard</h1>

<?php if ($isSuperAdmin): ?>
<div class="bot-panel" style="margin-bottom:24px">
    <a href="/admin" class="btn btn-ghost btn-sm">⚙ Admin-Bereich öffnen</a>
</div>
<?php endif; ?>

<div class="section-header">
    <h2>Meine Kanäle</h2>
    <div style="display:flex;gap:8px">
        <a href="<?= htmlspecialchars($inviteUrl, ENT_QUOTES) ?>" target="_blank" rel="noopener" class="btn btn-discord btn-sm">🤖 Bot einladen</a>
        <button class="btn btn-primary btn-sm" onclick="toggleAddChannel()">+ Kanal hinzufügen</button>
    </div>
</div>

<div id="add-channel-form" class="add-channel-panel" style="display:none">
    <h3>Kanal registrieren</h3>
    <p style="color:var(--muted);font-size:14px">Es werden nur Server angezeigt auf denen du Admin-Rechte hast <strong>und</strong> der Bot bereits eingeladen wurde.</p>
    <div class="form-row">
        <label>Server</label>
        <select id="guild-select" onchange="loadChannels()" disabled>
            <option value="">Lädt Server...</option>
        </select>
    </div>
    <div class="form-row">
        <label>Kanal</label>
        <select id="channel-select" disabled>
            <option value="">Zuerst Server wählen</option>
        </select>
    </div>
    <div id="channel-error" style="color:var(--danger);font-size:13px;display:none"></div>
    <div class="form-actions">
        <button class="btn btn-primary" onclick="registerChannel()">Registrieren</button>
        <button class="btn btn-ghost" onclick="toggleAddChannel()">Abbrechen</button>
    </div>
</div>

<?php if (empty($channels)): ?>
    <p style="color:var(--muted)">Noch keine Kanäle konfiguriert.</p>
<?php else: ?>
<div class="card-grid">
    <?php foreach ($channels as $ch): ?>
    <div class="card">
        <h3><?= htmlspecialchars($ch['guild_name'] ?? 'Unbekannter Server', ENT_QUOTES) ?></h3>
        <div class="meta">
            #<?= htmlspecialchars($ch['channel_name'] ?? $ch['channel_id'], ENT_QUOTES) ?>
            · <?= htmlspecialchars((string)$ch['feed_count'], ENT_QUOTES) ?> Feeds
            · <?= $ch['last_digest_at']
                ? 'Letzter Digest: ' . htmlspecialchars($fmtBerlin($ch['last_digest_at']), ENT_QUOTES)
                : 'Noch kein Digest' ?>
        </div>
        <div class="card-actions">
            <a href="/channel/<?= htmlspecialchars($ch['channel_id'], ENT_QUOTES) ?>" class="btn btn-primary">Konfigurieren</a>
            <button class="btn btn-danger btn-sm" onclick="removeChannel('<?= htmlspecialchars($ch['channel_id'], ENT_QUOTES) ?>', '<?= htmlspecialchars($ch['channel_name'] ?? $ch['channel_id'], ENT_QUOTES) ?>')">Entfernen</button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
const CSRF = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';

function toggleAddChannel() {
    const panel = document.getElementById('add-channel-form');
    const open  = panel.style.display === 'none';
    panel.style.display = open ? 'block' : 'none';
    if (open) loadGuilds();
}

async function loadGuilds() {
    const sel = document.getElementById('guild-select');
    sel.disabled = true;
    sel.innerHTML = '<option>Lädt...</option>';
    const guilds = await apiGet('/api/guilds');
    if (!guilds || guilds.error) {
        sel.innerHTML = '<option>Fehler beim Laden</option>';
        return;
    }
    sel.innerHTML = '<option value="">Server wählen...</option>'
        + guilds.map(g => `<option value="${escHtml(g.id)}" data-name="${escHtml(g.name)}">${escHtml(g.name)}</option>`).join('');
    sel.disabled = false;
}

async function loadChannels() {
    const guildSel   = document.getElementById('guild-select');
    const channelSel = document.getElementById('channel-select');
    const errEl      = document.getElementById('channel-error');
    const guildId    = guildSel.value;

    errEl.style.display = 'none';
    channelSel.disabled = true;
    channelSel.innerHTML = '<option>Lädt Kanäle...</option>';

    if (!guildId) {
        channelSel.innerHTML = '<option>Zuerst Server wählen</option>';
        return;
    }

    const channels = await apiGet(`/api/guilds/${guildId}/channels`);
    if (!channels || channels.error) {
        channelSel.innerHTML = '<option>Keine Kanäle gefunden</option>';
        errEl.textContent = channels?.error ?? 'Fehler beim Laden der Kanäle.';
        errEl.style.display = 'block';
        return;
    }

    channelSel.innerHTML = '<option value="">Kanal wählen...</option>'
        + channels.map(c => `<option value="${escHtml(c.id)}" data-name="${escHtml(c.name)}">#${escHtml(c.name)}</option>`).join('');
    channelSel.disabled = false;
}

async function registerChannel() {
    const guildSel   = document.getElementById('guild-select');
    const channelSel = document.getElementById('channel-select');
    const errEl      = document.getElementById('channel-error');

    const guildId    = guildSel.value;
    const guildName  = guildSel.selectedOptions[0]?.dataset.name ?? '';
    const channelId  = channelSel.value;
    const channelName = channelSel.selectedOptions[0]?.dataset.name ?? '';

    if (!guildId || !channelId) {
        errEl.textContent = 'Bitte Server und Kanal auswählen.';
        errEl.style.display = 'block';
        return;
    }

    const res = await apiPost('/api/channel/register', { guild_id: guildId, guild_name: guildName, channel_id: channelId, channel_name: channelName });
    if (res?.success) {
        location.reload();
    } else {
        errEl.textContent = res?.error ?? 'Fehler beim Registrieren.';
        errEl.style.display = 'block';
    }
}

async function removeChannel(channelId, channelName) {
    if (!confirm(`Kanal #${channelName} wirklich entfernen?`)) return;
    const res = await apiDelete(`/api/channel/${channelId}`);
    if (res?.success) {
        location.reload();
    } else {
        alert(res?.error ?? 'Fehler beim Entfernen.');
    }
}

async function apiGet(url) {
    const res = await fetch(url, { headers: { 'X-CSRF-Token': CSRF } });
    return res.json().catch(() => null);
}
</script>
