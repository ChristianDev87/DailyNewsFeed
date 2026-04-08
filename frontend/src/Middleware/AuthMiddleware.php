<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Auth;
use App\Database;
use App\Discord;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Psr7\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Auth     $auth,
        private Discord  $discord,
        private Database $db
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies      = $request->getCookieParams();
        $sessionToken = $cookies['session_token'] ?? null;

        if (!$sessionToken) {
            return $this->redirect('/');
        }

        $session = $this->auth->loadSession($sessionToken);
        if (!$session) {
            return $this->redirect('/');
        }

        // Access Token erneuern wenn < 1 Stunde Restlaufzeit
        if (strtotime($session['access_token_expires_at']) < time() + 3600) {
            if (!$this->auth->refreshAccessToken($sessionToken, $this->discord)) {
                $this->auth->destroySession($sessionToken);
                return $this->redirect('/');
            }
            $session = $this->auth->loadSession($sessionToken);
            if (!$session) {
                return $this->redirect('/');
            }
        }

        $this->auth->touchSession($sessionToken);

        $csrfToken = $this->auth->getCsrfToken($sessionToken);

        // CSRF-Check für alle nicht-GET Requests
        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            // Header hat Vorrang (AJAX), dann Form-Body
            $received = $request->getHeaderLine('X-CSRF-Token')
                ?: (((array)$request->getParsedBody())['csrf_token'] ?? '');

            if (!hash_equals($csrfToken, $received)) {
                $res = new Response();
                $res->getBody()->write(json_encode(['error' => 'CSRF-Validierung fehlgeschlagen']));
                return $res->withStatus(403)->withHeader('Content-Type', 'application/json');
            }
        }

        $heartbeat = $this->db->fetchOne('SELECT status, last_seen FROM bot_status LIMIT 1');
        $botOnline = $heartbeat
            && $heartbeat['status'] === 'online'
            && strtotime($heartbeat['last_seen']) > time() - 180;

        return $handler->handle(
            $request
                ->withAttribute('session', $session)
                ->withAttribute('session_token', $sessionToken)
                ->withAttribute('csrf_token', $csrfToken)
                ->withAttribute('is_superadmin', $this->auth->isSuperAdmin($session['discord_user_id']))
                ->withAttribute('bot_online', $botOnline)
        );
    }

    private function redirect(string $url): ResponseInterface
    {
        return (new Response())->withHeader('Location', $url)->withStatus(302);
    }
}
