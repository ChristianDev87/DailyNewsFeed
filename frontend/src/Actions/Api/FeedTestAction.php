<?php
declare(strict_types=1);

namespace App\Actions\Api;

use App\FeedValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class FeedTestAction
{
    public function __construct(private FeedValidator $validator) {}

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        $body = (array)$request->getParsedBody();
        $url  = trim($body['url'] ?? '');

        if (!$url) {
            return $this->json($response, ['valid' => false, 'error' => 'URL fehlt'], 400);
        }

        return $this->json($response, $this->validator->validateUrl($url));
    }

    private function json(Response $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
