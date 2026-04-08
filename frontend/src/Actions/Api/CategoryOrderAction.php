<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class CategoryOrderAction
{
    public function __construct(private Database $db) {}

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $session = $request->getAttribute('session');
        $body    = (array)$request->getParsedBody();
        $ids     = array_filter(array_map('intval', (array)($body['ids'] ?? [])));

        if (empty($ids)) {
            return $this->json($response, ['success' => false, 'error' => 'Keine IDs'], 400);
        }

        foreach (array_values($ids) as $position => $catId) {
            $this->db->execute(
                'UPDATE channel_categories cc
                 JOIN channels c ON c.channel_id = cc.channel_id
                 SET cc.position = ?
                 WHERE cc.id = ? AND c.owner_user_id = ?',
                [$position, $catId, $session['discord_user_id']]
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
