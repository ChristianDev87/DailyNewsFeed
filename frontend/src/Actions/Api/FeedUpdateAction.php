<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class FeedUpdateAction
{
    public function __construct(private Database $db) {}

    public function __invoke(ServerRequestInterface $request, Response $response, array $args): ResponseInterface
    {
        $session = $request->getAttribute('session');
        $feedId  = (int)($args['id'] ?? 0);
        $body    = (array)($request->getParsedBody() ?? json_decode((string)$request->getBody(), true) ?? []);

        $name     = trim($body['name']     ?? '');
        $url      = trim($body['url']      ?? '');
        $maxItems = max(1, min(20, (int)($body['max_items'] ?? 5)));

        if ($feedId <= 0 || $name === '' || $url === '') {
            return $this->json($response, ['success' => false, 'error' => 'Name und URL erforderlich'], 400);
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->json($response, ['success' => false, 'error' => 'Ungültige URL'], 400);
        }

        $row = $this->db->fetchOne(
            'SELECT cf.id FROM channel_feeds cf
             JOIN channel_categories cc ON cc.id = cf.category_id
             JOIN channels c ON c.channel_id = cc.channel_id
             WHERE cf.id = ? AND c.owner_user_id = ? AND c.active = 1',
            [$feedId, $session['discord_user_id']]
        );

        if (!$row) {
            return $this->json($response, ['success' => false, 'error' => 'Nicht gefunden'], 404);
        }

        $this->db->execute(
            'UPDATE channel_feeds SET name = ?, url = ?, max_items = ? WHERE id = ?',
            [$name, $url, $maxItems, $feedId]
        );

        return $this->json($response, ['success' => true]);
    }

    private function json(Response $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
