<?php
declare(strict_types=1);

namespace App\Actions;

use App\Config;
use App\Database;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

use function App\render;

class DashboardAction
{
    // VIEW_CHANNEL | SEND_MESSAGES | READ_MESSAGE_HISTORY | CREATE_PUBLIC_THREADS | SEND_MESSAGES_IN_THREADS
    private const BOT_PERMISSIONS = '309237711920';

    public function __construct(private Database $db) {}

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $session      = $request->getAttribute('session');
        $csrfToken    = $request->getAttribute('csrf_token');
        $isSuperAdmin = $request->getAttribute('is_superadmin');

        $channels = $this->db->fetchAll(
            'SELECT c.*,
                    COUNT(DISTINCT cf.id) AS feed_count,
                    MAX(dt.created_at) AS last_digest_at
             FROM channels c
             LEFT JOIN channel_categories cc ON cc.channel_id = c.channel_id AND cc.active = 1
             LEFT JOIN channel_feeds cf       ON cf.category_id = cc.id AND cf.active = 1
             LEFT JOIN daily_threads dt       ON dt.channel_id = c.channel_id
             WHERE c.owner_user_id = ? AND c.active = 1
             GROUP BY c.channel_id
             ORDER BY c.created_at DESC',
            [$session['discord_user_id']]
        );

        $botOnline = $request->getAttribute('bot_online');

        $inviteUrl = 'https://discord.com/oauth2/authorize?' . http_build_query([
            'client_id'   => Config::require('DISCORD_CLIENT_ID'),
            'permissions' => self::BOT_PERMISSIONS,
            'scope'       => 'bot applications.commands',
        ]);

        return render('dashboard', [
            'title'        => 'Dashboard',
            'session'      => $session,
            'csrfToken'    => $csrfToken,
            'channels'     => $channels,
            'isSuperAdmin' => $isSuperAdmin,
            'botOnline'    => $botOnline,
            'inviteUrl'    => $inviteUrl,
        ]);
    }
}
