<?php
declare(strict_types=1);

namespace App\Actions;

use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

use function App\render;

class ChannelAction
{
    public function __construct(private Database $db) {}

    public function __invoke(ServerRequestInterface $request, Response $response, array $args): ResponseInterface
    {
        $session   = $request->getAttribute('session');
        $csrfToken = $request->getAttribute('csrf_token');
        $channelId = $args['channel_id'];

        if (!ctype_digit($channelId)) {
            return $response->withStatus(400);
        }

        $channel = $this->db->fetchOne(
            'SELECT * FROM channels WHERE channel_id = ? AND owner_user_id = ? AND active = 1',
            [$channelId, $session['discord_user_id']]
        );

        if (!$channel) {
            $response->getBody()->write('Kanal nicht gefunden oder kein Zugriff.');
            return $response->withStatus(404);
        }

        $categories = $this->db->fetchAll(
            'SELECT * FROM channel_categories WHERE channel_id = ? ORDER BY position ASC, id ASC',
            [$channelId]
        );

        foreach ($categories as &$cat) {
            $cat['feeds'] = $this->db->fetchAll(
                'SELECT * FROM channel_feeds WHERE category_id = ? AND active = 1 ORDER BY id ASC',
                [$cat['id']]
            );
        }
        unset($cat);

        return render('channel', [
            'title'          => $channel['guild_name'] ?? 'Kanal-Konfiguration',
            'session'        => $session,
            'csrfToken'      => $csrfToken,
            'channel'        => $channel,
            'categories'     => $categories,
            'hasCustomToken' => !empty($channel['custom_bot_token_encrypted']),
            'botOnline'      => $request->getAttribute('bot_online'),
        ]);
    }
}
