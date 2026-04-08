<?php
/**
 * @var array  $channel
 * @var array  $categories
 * @var bool   $hasCustomToken
 * @var string $csrfToken
 */
$chId = htmlspecialchars($channel['channel_id'], ENT_QUOTES);
?>
<a href="/dashboard" style="color:var(--muted);font-size:14px;display:inline-block;margin-bottom:16px">← Dashboard</a>
<h1>⚙️ <?= htmlspecialchars($channel['guild_name'] ?? 'Server', ENT_QUOTES) ?> — #<?= htmlspecialchars($channel['channel_name'] ?? $channel['channel_id'], ENT_QUOTES) ?></h1>

<div id="cat-list" style="margin:24px 0">
<?php foreach ($categories as $cat):
    $catId = (int)$cat['id'];
?>
<div class="accordion" data-cat-id="<?= $catId ?>">
    <div class="accordion-header">
        <span>
            <span class="handle" title="Ziehen zum Sortieren">⠿</span>
            <span id="cat-label-<?= $catId ?>"><?= htmlspecialchars($cat['emoji'], ENT_QUOTES) ?> <strong><?= htmlspecialchars($cat['label'], ENT_QUOTES) ?></strong></span>
            <span style="color:var(--muted);font-size:13px;margin-left:8px" id="cat-count-<?= $catId ?>">(<?= count($cat['feeds']) ?> Feeds)</span>
        </span>
        <span style="display:flex;align-items:center;gap:6px">
            <button class="btn btn-ghost" style="padding:3px 9px;font-size:12px"
                data-cat-id="<?= $catId ?>"
                data-label="<?= htmlspecialchars($cat['label'], ENT_QUOTES) ?>"
                data-emoji="<?= htmlspecialchars($cat['emoji'], ENT_QUOTES) ?>"
                onclick="editCategory(this, event)">✎</button>
            <button class="btn btn-danger" style="padding:3px 9px;font-size:12px"
                onclick="deleteCategory(<?= $catId ?>, event)">✕</button>
            <span style="color:var(--muted)">▼</span>
        </span>
    </div>
    <div class="accordion-body">
        <div id="feeds-<?= $catId ?>">
        <?php foreach ($cat['feeds'] as $feed):
            $fId = (int)$feed['id'];
        ?>
            <div id="fwrap-<?= $fId ?>">
                <div class="feed-item">
                    <span>
                        <span id="fname-disp-<?= $fId ?>"><?= htmlspecialchars($feed['name'], ENT_QUOTES) ?></span>
                        <span style="color:var(--muted);font-size:12px;margin-left:4px" id="furl-disp-<?= $fId ?>"><?= htmlspecialchars($feed['url'], ENT_QUOTES) ?></span>
                        <span style="color:var(--muted);font-size:12px;margin-left:4px">max: <span id="fmax-disp-<?= $fId ?>"><?= (int)$feed['max_items'] ?></span></span>
                    </span>
                    <span style="display:flex;gap:4px">
                        <button class="btn btn-ghost" style="padding:3px 8px;font-size:12px"
                            onclick="toggleEditFeed(<?= $fId ?>)">✎</button>
                        <button class="btn btn-danger" style="padding:3px 8px;font-size:12px"
                            onclick="deleteFeed(<?= $fId ?>)">✕</button>
                    </span>
                </div>
                <div id="fedit-<?= $fId ?>" style="display:none;padding:8px 12px;background:var(--bg);border-radius:var(--radius);margin-bottom:6px">
                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                        <input type="text"   id="fedit-name-<?= $fId ?>" value="<?= htmlspecialchars($feed['name'], ENT_QUOTES) ?>" placeholder="Name" style="flex:1;min-width:100px">
                        <input type="url"    id="fedit-url-<?= $fId ?>"  value="<?= htmlspecialchars($feed['url'], ENT_QUOTES) ?>"  placeholder="Feed-URL" style="flex:2;min-width:160px">
                        <input type="number" id="fedit-max-<?= $fId ?>"  value="<?= (int)$feed['max_items'] ?>" min="1" max="20" style="width:70px" title="Max. Artikel pro Lauf">
                        <button class="btn btn-primary" style="padding:4px 12px;font-size:13px" onclick="saveFeed(<?= $fId ?>)">Speichern</button>
                        <button class="btn btn-ghost"   style="padding:4px 12px;font-size:13px" onclick="toggleEditFeed(<?= $fId ?>)">Abbrechen</button>
                    </div>
                    <div id="fedit-status-<?= $fId ?>" style="font-size:12px;margin-top:4px;min-height:16px"></div>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="feed-add-row">
            <input type="url"  id="furl-<?= $catId ?>" placeholder="Feed-URL...">
            <input type="text" id="fnameinput-<?= $catId ?>" placeholder="Name" style="max-width:140px">
            <button class="btn btn-ghost"   onclick="testFeed(<?= $catId ?>)">Testen</button>
            <button class="btn btn-primary" onclick="addFeed(<?= $catId ?>)">+ Hinzufügen</button>
        </div>
        <div id="fstatus-<?= $catId ?>" style="font-size:13px;margin-top:6px;min-height:18px"></div>
    </div>
</div>
<?php endforeach; ?>
</div>

<button class="btn btn-ghost" onclick="newCategory('<?= $chId ?>')">+ Neue Kategorie</button>

<!-- Bot-Token -->
<div style="margin-top:40px;padding-top:24px;border-top:1px solid var(--border)">
    <h2>Eigener Bot-Token (optional)</h2>
    <p style="color:var(--muted);font-size:14px;margin-bottom:16px">Wird AES-256 verschlüsselt gespeichert. Nur der Bot entschlüsselt ihn.</p>
    <div style="display:flex;gap:8px;max-width:500px">
        <input type="password" id="bot-token" placeholder="<?= $hasCustomToken ? '••••••••' : 'Token eingeben...' ?>">
        <button class="btn btn-primary" onclick="saveBotToken('<?= $chId ?>')">Speichern</button>
        <?php if ($hasCustomToken): ?>
        <button class="btn btn-danger" onclick="removeBotToken('<?= $chId ?>')">Entfernen</button>
        <?php endif; ?>
    </div>
    <div id="token-status" style="font-size:13px;margin-top:8px"></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script>
const CSRF = '<?= htmlspecialchars($csrfToken, ENT_QUOTES) ?>';

new Sortable(document.getElementById('cat-list'), {
    handle: '.handle',
    animation: 150,
    onEnd: async () => {
        const ids = [...document.querySelectorAll('#cat-list .accordion')]
            .map(el => parseInt(el.dataset.catId));
        await fetch('/api/category/order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ ids }),
        });
    },
});

async function testFeed(catId) {
    const url    = document.getElementById(`furl-${catId}`).value.trim();
    const status = document.getElementById(`fstatus-${catId}`);
    if (!url) return;
    status.textContent = 'Prüfe...';
    const data = await apiPost('/api/feed/test', { url });
    status.textContent = data.valid ? '✓ Gültiger Feed' : `✗ ${data.error}`;
    status.style.color = data.valid ? 'var(--success)' : 'var(--danger)';
}

async function addFeed(catId) {
    const url    = document.getElementById(`furl-${catId}`).value.trim();
    const name   = document.getElementById(`fnameinput-${catId}`).value.trim();
    const status = document.getElementById(`fstatus-${catId}`);
    if (!url || !name) { status.textContent = 'URL und Name erforderlich.'; return; }

    const data = await apiPost('/api/feed/save', { category_id: catId, url, name });
    if (data.success) {
        const id = data.id;
        document.getElementById(`feeds-${catId}`).insertAdjacentHTML('beforeend',
            `<div id="fwrap-${id}">
                <div class="feed-item">
                    <span>
                        <span id="fname-disp-${id}">${escHtml(name)}</span>
                        <span style="color:var(--muted);font-size:12px;margin-left:4px" id="furl-disp-${id}">${escHtml(url)}</span>
                        <span style="color:var(--muted);font-size:12px;margin-left:4px">max: <span id="fmax-disp-${id}">5</span></span>
                    </span>
                    <span style="display:flex;gap:4px">
                        <button class="btn btn-ghost" style="padding:3px 8px;font-size:12px" onclick="toggleEditFeed(${id})">✎</button>
                        <button class="btn btn-danger" style="padding:3px 8px;font-size:12px" onclick="deleteFeed(${id})">✕</button>
                    </span>
                </div>
                <div id="fedit-${id}" style="display:none;padding:8px 12px;background:var(--bg);border-radius:var(--radius);margin-bottom:6px">
                    <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center">
                        <input type="text"   id="fedit-name-${id}" value="${escHtml(name)}" placeholder="Name" style="flex:1;min-width:100px">
                        <input type="url"    id="fedit-url-${id}"  value="${escHtml(url)}"  placeholder="Feed-URL" style="flex:2;min-width:160px">
                        <input type="number" id="fedit-max-${id}"  value="5" min="1" max="20" style="width:70px">
                        <button class="btn btn-primary" style="padding:4px 12px;font-size:13px" onclick="saveFeed(${id})">Speichern</button>
                        <button class="btn btn-ghost"   style="padding:4px 12px;font-size:13px" onclick="toggleEditFeed(${id})">Abbrechen</button>
                    </div>
                    <div id="fedit-status-${id}" style="font-size:12px;margin-top:4px;min-height:16px"></div>
                </div>
            </div>`
        );
        document.getElementById(`furl-${catId}`).value = '';
        document.getElementById(`fnameinput-${catId}`).value = '';
        status.textContent = '✓ Feed hinzugefügt';
        status.style.color = 'var(--success)';
    } else {
        status.textContent = `✗ ${data.error ?? 'Fehler'}`;
        status.style.color = 'var(--danger)';
    }
}

function toggleEditFeed(id) {
    const edit = document.getElementById(`fedit-${id}`);
    edit.style.display = edit.style.display === 'none' ? 'block' : 'none';
}

async function saveFeed(id) {
    const name     = document.getElementById(`fedit-name-${id}`).value.trim();
    const url      = document.getElementById(`fedit-url-${id}`).value.trim();
    const maxItems = parseInt(document.getElementById(`fedit-max-${id}`).value) || 5;
    const status   = document.getElementById(`fedit-status-${id}`);

    if (!name || !url) { status.textContent = 'Name und URL erforderlich.'; status.style.color = 'var(--danger)'; return; }

    const data = await apiPut(`/api/feed/${id}`, { name, url, max_items: maxItems });
    if (data.success) {
        document.getElementById(`fname-disp-${id}`).textContent = name;
        document.getElementById(`furl-disp-${id}`).textContent  = url;
        document.getElementById(`fmax-disp-${id}`).textContent  = maxItems;
        toggleEditFeed(id);
    } else {
        status.textContent = `✗ ${data.error ?? 'Fehler'}`;
        status.style.color = 'var(--danger)';
    }
}

async function editCategory(btn, e) {
    e.stopPropagation();
    const id    = parseInt(btn.dataset.catId);
    const label = prompt('Kategorie-Name:', btn.dataset.label);
    if (label === null || label.trim() === '') return;
    const emoji = prompt('Emoji:', btn.dataset.emoji);

    const data = await apiPut(`/api/category/${id}`, { label: label.trim(), emoji: (emoji || '📰').trim() });
    if (data.success) {
        btn.dataset.label = data.label;
        btn.dataset.emoji = data.emoji;
        document.getElementById(`cat-label-${id}`).innerHTML =
            `${escHtml(data.emoji)} <strong>${escHtml(data.label)}</strong>`;
    } else {
        alert(`Fehler: ${data.error}`);
    }
}

async function newCategory(channelId) {
    const label = prompt('Kategorie-Name:');
    if (!label) return;
    const emoji = prompt('Emoji (z.B. 🤖):', '📰');
    const data  = await apiPost('/api/category/save', { channel_id: channelId, label, emoji: emoji || '📰' });
    if (data.success) location.reload();
    else alert(`Fehler: ${data.error}`);
}

async function saveBotToken(channelId) {
    const token = document.getElementById('bot-token').value.trim();
    if (!token) return;
    const data = await apiPost('/api/category/save', { action: 'save_token', channel_id: channelId, token });
    const el   = document.getElementById('token-status');
    el.textContent = data.success ? '✓ Token gespeichert.' : `✗ ${data.error}`;
    el.style.color = data.success ? 'var(--success)' : 'var(--danger)';
    if (data.success) document.getElementById('bot-token').value = '';
}

async function removeBotToken(channelId) {
    if (!confirm('Token wirklich entfernen?')) return;
    const data = await apiPost('/api/category/save', { action: 'remove_token', channel_id: channelId });
    if (data.success) location.reload();
}
</script>
