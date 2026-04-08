<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Auth;
use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class CategorySaveAction
{
    public function __construct(
        private Database $db,
        private Auth     $auth
    ) {}

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $session = $request->getAttribute('session');
        $body    = (array)$request->getParsedBody();

        return match ($body['action'] ?? 'create_category') {
            'save_token'   => $this->saveBotToken($response, $session, $body),
            'remove_token' => $this->removeBotToken($response, $session, $body),
            default        => $this->createCategory($response, $session, $body),
        };
    }

    private function createCategory(Response $response, array $session, array $body): ResponseInterface
    {
        $channelId = trim($body['channel_id'] ?? '');
        $label     = trim($body['label'] ?? '');
        $emoji     = trim($body['emoji'] ?? '📰');

        if (!$channelId || !$label) {
            return $this->json($response, ['success' => false, 'error' => 'Fehlende Felder'], 400);
        }

        $channel = $this->db->fetchOne(
            'SELECT channel_id FROM channels WHERE channel_id = ? AND owner_user_id = ?',
            [$channelId, $session['discord_user_id']]
        );
        if (!$channel) {
            return $this->json($response, ['success' => false, 'error' => 'Kein Zugriff'], 403);
        }

        $maxPos = $this->db->fetchOne(
            'SELECT COALESCE(MAX(position), 0) AS p FROM channel_categories WHERE channel_id = ?',
            [$channelId]
        );

        $this->db->execute(
            'INSERT INTO channel_categories (channel_id, label, emoji, position, active, use_default)
             VALUES (?, ?, ?, ?, 1, 0)',
            [$channelId, $label, $emoji, (int)($maxPos['p'] ?? 0) + 1]
        );

        return $this->json($response, ['success' => true, 'id' => (int)$this->db->lastInsertId()]);
    }

    private function saveBotToken(Response $response, array $session, array $body): ResponseInterface
    {
        $channelId = trim($body['channel_id'] ?? '');
        $token     = trim($body['token'] ?? '');

        if (!$channelId || !$token) {
            return $this->json($response, ['success' => false, 'error' => 'Fehlende Felder'], 400);
        }

        $channel = $this->db->fetchOne(
            'SELECT channel_id FROM channels WHERE channel_id = ? AND owner_user_id = ?',
            [$channelId, $session['discord_user_id']]
        );
        if (!$channel) {
            return $this->json($response, ['success' => false, 'error' => 'Kein Zugriff'], 403);
        }

        $enc = $this->auth->encryptBotToken($token);
        $this->db->execute(
            'UPDATE channels SET custom_bot_token_encrypted = ?, custom_bot_token_iv = ? WHERE channel_id = ?',
            [$enc['encrypted'], $enc['iv'], $channelId]
        );

        return $this->json($response, ['success' => true]);
    }

    private function removeBotToken(Response $response, array $session, array $body): ResponseInterface
    {
        $channelId = trim($body['channel_id'] ?? '');

        if (!$channelId) {
            return $this->json($response, ['success' => false, 'error' => 'Fehlende Felder'], 400);
        }

        $channel = $this->db->fetchOne(
            'SELECT channel_id FROM channels WHERE channel_id = ? AND owner_user_id = ?',
            [$channelId, $session['discord_user_id']]
        );
        if (!$channel) {
            return $this->json($response, ['success' => false, 'error' => 'Kein Zugriff'], 403);
        }

        $this->db->execute(
            'UPDATE channels SET custom_bot_token_encrypted = NULL, custom_bot_token_iv = NULL
             WHERE channel_id = ?',
            [$channelId]
        );

        return $this->json($response, ['success' => true]);
    }

    private function json(Response $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
