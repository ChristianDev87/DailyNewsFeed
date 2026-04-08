<?php
declare(strict_types=1);

namespace App\Actions\Auth;

use App\Auth;
use App\Discord;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class CallbackAction
{
    public function __construct(
        private Auth    $auth,
        private Discord $discord
    ) {}

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $params = $request->getQueryParams();
        $code   = $params['code']  ?? null;
        $state  = $params['state'] ?? null;

        $expectedState = $request->getCookieParams()['oauth_state'] ?? '';
        if (!$code || !$state || !hash_equals($expectedState, $state)) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $tokens = $this->discord->exchangeCode($code);
        if (empty($tokens['access_token'])) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        $user = $this->discord->getUser($tokens['access_token']);
        if (empty($user['id'])) {
            return $response->withHeader('Location', '/')->withStatus(302);
        }

        // Session anlegen
        $sessionToken = $this->auth->createSession(
            $user['id'],
            $user['global_name'] ?? $user['username'] ?? 'Unbekannt',
            $tokens['access_token'],
            $tokens['refresh_token'] ?? '',
            (int)($tokens['expires_in'] ?? 604800)
        );

        $secure = $request->getUri()->getScheme() === 'https' ? '; Secure' : '';
        $sessionCookie = sprintf(
            'session_token=%s; Path=/; HttpOnly; SameSite=Lax%s; Max-Age=%d',
            $sessionToken,
            $secure,
            60 * 60 * 24 * 7
        );
        $clearState = 'oauth_state=; Path=/auth/callback; HttpOnly; SameSite=Lax; Max-Age=0';

        return $response
            ->withAddedHeader('Set-Cookie', $sessionCookie)
            ->withAddedHeader('Set-Cookie', $clearState)
            ->withHeader('Location', '/dashboard')
            ->withStatus(302);
    }
}
