<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\Discord;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class GuildChannelsAction
{
    public function __construct(private Discord $discord) {}

    public function __invoke(ServerRequestInterface $request, Response $response, array $args): ResponseInterface
    {
        $guildId = $args['guild_id'] ?? '';
        if (!ctype_digit($guildId)) {
            $response->getBody()->write(json_encode(['error' => 'Ungültige Guild-ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $channels = $this->discord->getGuildChannels($guildId);

        if (empty($channels)) {
            $response->getBody()->write(json_encode(['error' => 'Bot nicht auf diesem Server oder keine Text-Kanäle gefunden']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        $result = array_map(fn($ch) => [
            'id'       => $ch['id'],
            'name'     => $ch['name'],
            'position' => $ch['position'] ?? 0,
        ], $channels);

        usort($result, fn($a, $b) => $a['position'] <=> $b['position']);

        $response->getBody()->write(json_encode(array_values($result)));
        return $response->withHeader('Content-Type', 'application/json');
    }
}
