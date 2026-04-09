<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class FeedDeleteAction
{
    public function __construct(private Database $db) {}

    public function __invoke(ServerRequestInterface $request, Response $response, array $args): ResponseInterface
    {
        $session = $request->getAttribute('session');
        $feedId  = (int)($args['id'] ?? 0);

        if (!$feedId) {
            return $this->json($response, ['success' => false, 'error' => 'Ungültige ID'], 400);
        }

        // Sicherstellen dass Feed dem eingeloggten Nutzer gehört
        $feed = $this->db->fetchOne(
            'SELECT cf.id FROM channel_feeds cf
             JOIN channel_categories cc ON cc.id = cf.category_id
             JOIN channels c ON c.channel_id = cc.channel_id
             WHERE cf.id = ? AND c.owner_user_id = ?',
            [$feedId, $session['discord_user_id']]
        );

        if (!$feed) {
            return $this->json($response, ['success' => false, 'error' => 'Feed nicht gefunden'], 404);
        }

        $this->db->execute('DELETE FROM channel_feeds WHERE id = ?', [$feedId]);

        return $this->json($response, ['success' => true]);
    }

    private function json(Response $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
