<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class BotCommandAction
{
    private const ALLOWED = ['restart_bot', 'run_digest', 'stop_bot', 'deploy_bot', 'deploy_frontend'];

    public function __construct(private Database $db) {}

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        if (!$request->getAttribute('is_superadmin')) {
            return $this->json($response, ['success' => false, 'error' => 'Kein Zugriff'], 403);
        }

        $session = $request->getAttribute('session');
        $body    = (array)$request->getParsedBody();
        $command = trim($body['command'] ?? '');

        if (!in_array($command, self::ALLOWED, true)) {
            return $this->json($response, ['success' => false, 'error' => 'Unbekannter Befehl'], 400);
        }

        $this->db->execute(
            'INSERT INTO bot_commands (command, status, created_by, created_at) VALUES (?, ?, ?, NOW())',
            [$command, 'pending', $session['discord_user_id']]
        );

        return $this->json($response, [
            'success' => true,
            'message' => match ($command) {
                'restart_bot'     => 'Neustart-Befehl gesendet.',
                'run_digest'      => 'Digest wird ausgeführt.',
                'stop_bot'        => 'Stop-Befehl gesendet.',
                'deploy_bot'      => 'Bot-Deploy gesendet — läuft im Hintergrund (~1–2 Min).',
                'deploy_frontend' => 'Frontend-Deploy gesendet — läuft im Hintergrund (~1–2 Min).',
            },
        ]);
    }

    private function json(Response $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
