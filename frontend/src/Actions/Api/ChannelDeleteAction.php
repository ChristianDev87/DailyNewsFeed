<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class ChannelDeleteAction
{
    public function __construct(private Database $db) {}

    public function __invoke(ServerRequestInterface $request, Response $response, array $args): ResponseInterface
    {
        $session   = $request->getAttribute('session');
        $channelId = $args['channel_id'] ?? '';

        if (!ctype_digit($channelId)) {
            return $this->json($response, ['success' => false, 'error' => 'Ungültige Kanal-ID'], 400);
        }

        $channel = $this->db->fetchOne(
            'SELECT channel_id FROM channels WHERE channel_id = ? AND owner_user_id = ? AND active = 1',
            [$channelId, $session['discord_user_id']]
        );

        if (!$channel) {
            return $this->json($response, ['success' => false, 'error' => 'Kanal nicht gefunden'], 404);
        }

        $this->db->execute(
            'UPDATE channels SET active = 0 WHERE channel_id = ?',
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
