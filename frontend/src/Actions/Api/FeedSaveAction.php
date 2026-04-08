<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Database;
use App\FeedValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class FeedSaveAction
{
    public function __construct(
        private Database      $db,
        private FeedValidator $validator
    ) {}

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $session    = $request->getAttribute('session');
        $body       = (array)$request->getParsedBody();
        $categoryId = (int)($body['category_id'] ?? 0);
        $url        = trim($body['url'] ?? '');
        $name       = trim($body['name'] ?? '');

        if (!$categoryId || !$url || !$name) {
            return $this->json($response, ['success' => false, 'error' => 'Fehlende Felder'], 400);
        }

        // Prüfen ob Kategorie dem eingeloggten Nutzer gehört
        $cat = $this->db->fetchOne(
            'SELECT cc.id FROM channel_categories cc
             JOIN channels c ON c.channel_id = cc.channel_id
             WHERE cc.id = ? AND c.owner_user_id = ?',
            [$categoryId, $session['discord_user_id']]
        );

        if (!$cat) {
            return $this->json($response, ['success' => false, 'error' => 'Kategorie nicht gefunden'], 404);
        }

        $validation = $this->validator->validateUrl($url);
        if (!$validation['valid']) {
            return $this->json($response, ['success' => false, 'error' => $validation['error']], 422);
        }

        $this->db->execute(
            'INSERT INTO channel_feeds (category_id, name, url, max_items, active) VALUES (?, ?, ?, 5, 1)',
            [$categoryId, $name, $url]
        );

        return $this->json($response, ['success' => true, 'id' => (int)$this->db->lastInsertId()]);
    }

    private function json(Response $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
