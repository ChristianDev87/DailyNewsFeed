<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Database;
use App\Discord;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class ChannelRegisterAction
{
    public function __construct(
        private Database $db,
        private Discord  $discord
    ) {}

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $session = $request->getAttribute('session');
        $body    = (array)$request->getParsedBody();

        $channelId   = trim($body['channel_id']   ?? '');
        $guildId     = trim($body['guild_id']      ?? '');
        $guildName   = trim($body['guild_name']    ?? '');
        $channelName = trim($body['channel_name']  ?? '');

        if (!ctype_digit($channelId) || !ctype_digit($guildId) || $guildName === '' || $channelName === '') {
            return $this->json($response, ['success' => false, 'error' => 'Pflichtfelder fehlen'], 400);
        }

        // Prüfen ob Kanal bereits registriert (aktiv oder soft-gelöscht)
        $existing = $this->db->fetchOne(
            'SELECT channel_id, active FROM channels WHERE channel_id = ?',
            [$channelId]
        );
        if ($existing && (int)$existing['active'] === 1) {
            return $this->json($response, ['success' => false, 'error' => 'Kanal ist bereits registriert'], 409);
        }

        // Verifizieren: Bot ist auf dem Server und Kanal existiert
        $botChannels = $this->discord->getGuildChannels($guildId);
        $valid = array_filter($botChannels, fn($ch) => $ch['id'] === $channelId);
        if (empty($valid)) {
            return $this->json($response, [
                'success' => false,
                'error'   => 'Kanal nicht gefunden. Stelle sicher dass der Bot auf dem Server ist.',
            ], 422);
        }

        if ($existing) {
            // Kanal war gelöscht — reaktivieren
            $this->db->execute(
                'UPDATE channels SET active = 1, owner_user_id = ?, guild_name = ?, channel_name = ? WHERE channel_id = ?',
                [$session['discord_user_id'], $guildName, $channelName, $channelId]
            );
        } else {
            $this->db->execute(
                'INSERT INTO channels (channel_id, guild_id, guild_name, channel_name, owner_user_id, active, created_at)
                 VALUES (?, ?, ?, ?, ?, 1, NOW())',
                [$channelId, $guildId, $guildName, $channelName, $session['discord_user_id']]
            );
        }

        return $this->json($response, ['success' => true]);
    }

    private function json(Response $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
