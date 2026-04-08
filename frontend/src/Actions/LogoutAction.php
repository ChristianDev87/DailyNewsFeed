<?php
declare(strict_types=1);

namespace App\Actions;

use App\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class LogoutAction
{
    public function __construct(private Auth $auth) {}

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $token = $request->getCookieParams()['session_token'] ?? null;

        if ($token) {
            $this->auth->destroySession($token);
        }

        $clearCookie = 'session_token=; Path=/; HttpOnly; SameSite=Strict; Max-Age=0';

        return $response
            ->withAddedHeader('Set-Cookie', $clearCookie)
            ->withHeader('Location', '/')
            ->withStatus(302);
    }
}
