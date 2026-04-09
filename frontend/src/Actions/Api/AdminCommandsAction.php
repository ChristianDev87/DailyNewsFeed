<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class AdminCommandsAction
{
    public function __construct(private Database $db) {}

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        if (!$request->getAttribute('is_superadmin')) {
            return $this->json($response, ['error' => 'Kein Zugriff'], 403);
        }

        $pendingCount = (int)$this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM bot_commands WHERE status = "pending"'
        )['n'];

        $articlesToday = (int)$this->db->fetchOne(
            'SELECT COUNT(*) AS n FROM seen_articles WHERE DATE(seen_at) = CURDATE()'
        )['n'];

        $commands = $this->db->fetchAll(
            'SELECT id, command, status, created_by, created_at, executed_at
             FROM bot_commands ORDER BY id DESC LIMIT 20'
        );

        return $this->json($response, [
            'pending_count'  => $pendingCount,
            'articles_today' => $articlesToday,
            'commands'       => $commands,
        ]);
    }

    private function json(Response $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
