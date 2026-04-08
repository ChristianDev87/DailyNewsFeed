<?php
declare(strict_types=1);

namespace App\Actions;

use App\Auth;
use App\Discord;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

use function App\render;

class LoginAction
{
    public function __construct(
        private Auth    $auth,
        private Discord $discord
    ) {}

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        // Bereits eingeloggt → Dashboard
        $token = $request->getCookieParams()['session_token'] ?? null;
        if ($token && $this->auth->loadSession($token)) {
            return $response->withHeader('Location', '/dashboard')->withStatus(302);
        }

        // OAuth2 State für CSRF-Schutz des Login-Flows
        $state   = bin2hex(random_bytes(16));
        $authUrl = $this->discord->buildAuthUrl($state);

        $secure      = $request->getUri()->getScheme() === 'https' ? '; Secure' : '';
        $stateCookie = sprintf(
            'oauth_state=%s; Path=/auth/callback; HttpOnly; SameSite=Lax%s; Max-Age=600',
            $state,
            $secure
        );

        return render('login', ['authUrl' => $authUrl])
            ->withAddedHeader('Set-Cookie', $stateCookie);
    }
}
