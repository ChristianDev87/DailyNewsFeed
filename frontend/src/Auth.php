<?php
declare(strict_types=1);

namespace App;

class Auth
{
    public function __construct(private Database $db) {}

    public function createSession(
        string $discordUserId,
        string $discordUsername,
        string $accessToken,
        string $refreshToken,
        int    $accessTokenExpiresIn
    ): string {
        $token  = bin2hex(random_bytes(64));
        $now    = new \DateTimeImmutable();
        $safeTtl = min(max($accessTokenExpiresIn, 1), 604800);
        $atExp   = $now->modify("+{$safeTtl} seconds")->format('Y-m-d H:i:s');
        $rtExp  = $now->modify('+30 days')->format('Y-m-d H:i:s');
        $sesExp = $now->modify('+7 days')->format('Y-m-d H:i:s');

        $this->db->execute(
            'INSERT INTO sessions
                (session_token, discord_user_id, discord_username, access_token,
                 access_token_expires_at, refresh_token, refresh_token_expires_at,
                 session_expires_at, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [$token, $discordUserId, $discordUsername, $accessToken, $atExp, $refreshToken, $rtExp, $sesExp]
        );

        return $token;
    }

    public function loadSession(string $token): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM sessions WHERE session_token = ? AND session_expires_at > NOW()',
            [$token]
        );
    }

    public function refreshAccessToken(string $token, Discord $discord): bool
    {
        $session = $this->db->fetchOne(
            'SELECT * FROM sessions WHERE session_token = ? AND session_expires_at > NOW()',
            [$token]
        );

        if (!$session || strtotime($session['refresh_token_expires_at']) < time()) {
            return false;
        }

        $tokens = $discord->refreshToken($session['refresh_token']);
        if (empty($tokens['access_token'])) {
            return false;
        }

        $atExp = (new \DateTimeImmutable())
            ->modify('+' . ($tokens['expires_in'] ?? 604800) . ' seconds')
            ->format('Y-m-d H:i:s');

        $rtExp = (new \DateTimeImmutable())->modify('+30 days')->format('Y-m-d H:i:s');

        $this->db->execute(
            'UPDATE sessions SET access_token = ?, access_token_expires_at = ?,
             refresh_token = ?, refresh_token_expires_at = ?
             WHERE session_token = ?',
            [
                $tokens['access_token'],
                $atExp,
                $tokens['refresh_token'] ?? $session['refresh_token'],
                $rtExp,
                $token,
            ]
        );

        return true;
    }

    /**
     * Sliding Session: nur updaten wenn Ablaufzeit sich um mehr als 1 Stunde ändert.
     */
    public function touchSession(string $token): void
    {
        $newExpiry = (new \DateTimeImmutable())->modify('+7 days')->format('Y-m-d H:i:s');
        $this->db->execute(
            'UPDATE sessions SET session_expires_at = ?
             WHERE session_token = ? AND session_expires_at < DATE_SUB(?, INTERVAL 1 HOUR)',
            [$newExpiry, $token, $newExpiry]
        );
    }

    public function destroySession(string $token): void
    {
        $this->db->execute('DELETE FROM sessions WHERE session_token = ?', [$token]);
    }

    /**
     * CSRF-Token stateless aus Session-Token ableiten (HMAC-SHA256).
     * Stateless by design: same token for session lifetime. Per spec.
     */
    public function getCsrfToken(string $sessionToken): string
    {
        return hash_hmac(
            'sha256',
            'csrf:' . $sessionToken,
            $this->encryptionKey()
        );
    }

    /**
     * Bot-Token AES-256-CBC verschlüsseln.
     * Gibt ['encrypted' => string, 'iv' => string (base64)] zurück.
     */
    public function encryptBotToken(string $token): array
    {
        $iv        = random_bytes(16);
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $this->encryptionKey(), 0, $iv);
        if ($encrypted === false) {
            throw new \RuntimeException('Bot-Token-Verschlüsselung fehlgeschlagen');
        }

        return ['encrypted' => $encrypted, 'iv' => base64_encode($iv)];
    }

    private function encryptionKey(): string
    {
        $key = base64_decode(Config::require('TOKEN_ENCRYPTION_KEY'));
        if (strlen($key) !== 32) {
            throw new \RuntimeException('TOKEN_ENCRYPTION_KEY muss 32 Bytes (base64-kodiert) ergeben');
        }
        return $key;
    }

    /**
     * Prüft ob Discord User ID in SUPERADMIN_IDS aus .env steht.
     */
    public function isSuperAdmin(string $discordUserId): bool
    {
        $ids = array_filter(array_map('trim', explode(',', Config::get('SUPERADMIN_IDS'))));
        return in_array($discordUserId, $ids, true);
    }
}
