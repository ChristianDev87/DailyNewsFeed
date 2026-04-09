<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class BotCommandStatusAction
{
    public function __construct(private Database $db) {}

    public function __invoke(ServerRequestInterface $request, Response $response, array $args): ResponseInterface
    {
        if (!$request->getAttribute('is_superadmin')) {
            return $this->json($response, ['error' => 'Kein Zugriff'], 403);
        }

        $id  = (int)($args['id'] ?? 0);
        if ($id <= 0) {
            return $this->json($response, ['error' => 'Ungültige ID'], 400);
        }
        $row = $this->db->fetchOne('SELECT status FROM bot_commands WHERE id = ?', [$id]);

        if ($row === null) {
            return $this->json($response, ['error' => 'Nicht gefunden'], 404);
        }

        return $this->json($response, ['status' => $row['status']]);
    }

    private function json(Response $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
