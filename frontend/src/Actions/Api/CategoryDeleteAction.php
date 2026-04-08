<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class CategoryDeleteAction
{
    public function __construct(private Database $db) {}

    public function __invoke(ServerRequestInterface $request, Response $response, array $args): ResponseInterface
    {
        $session    = $request->getAttribute('session');
        $categoryId = (int)($args['id'] ?? 0);

        if ($categoryId <= 0) {
            return $this->json($response, ['success' => false, 'error' => 'Ungültige ID'], 400);
        }

        // Ownership: Kategorie → Kanal → owner_user_id
        $row = $this->db->fetchOne(
            'SELECT cc.id FROM channel_categories cc
             JOIN channels c ON c.channel_id = cc.channel_id
             WHERE cc.id = ? AND c.owner_user_id = ? AND c.active = 1',
            [$categoryId, $session['discord_user_id']]
        );

        if (!$row) {
            return $this->json($response, ['success' => false, 'error' => 'Nicht gefunden'], 404);
        }

        // Hard delete — Feeds werden via ON DELETE CASCADE mitgelöscht
        $this->db->execute('DELETE FROM channel_categories WHERE id = ?', [$categoryId]);

        return $this->json($response, ['success' => true]);
    }

    private function json(Response $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
