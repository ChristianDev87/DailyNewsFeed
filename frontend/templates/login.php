<?php /** @var string $authUrl */ ?>
<div class="login-page">
    <div class="login-box">
        <h1>📰 Daily News</h1>
        <p>Verwalte deine Discord-Kanäle, RSS-Feeds und Bot-Konfiguration.</p>
        <a href="<?= htmlspecialchars($authUrl, ENT_QUOTES) ?>" class="btn btn-discord">
            Mit Discord anmelden
        </a>
        <div class="login-permissions">
            <p>Beim Anmelden erteilst du folgende Berechtigungen:</p>
            <ul>
                <li><strong>Profil lesen</strong> — Dein Discord-Nutzername und Avatar</li>
                <li><strong>Serverliste lesen</strong> — Die Server, auf denen du Mitglied bist</li>
            </ul>
            <p class="login-note">Es werden keine Nachrichten gelesen oder gesendet. Der Zugriff kann jederzeit unter <a href="https://discord.com/settings/authorized-apps" target="_blank" rel="noopener">Discord → Autorisierte Apps</a> widerrufen werden.</p>
        </div>
    </div>
</div>
