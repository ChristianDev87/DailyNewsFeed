<?php
declare(strict_types=1);

namespace App;

use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Response;

/**
 * Template rendern und in Layout einbetten.
 * $vars werden im Template-Scope verfügbar gemacht.
 */
function render(string $template, array $vars = [], int $status = 200): ResponseInterface
{
    extract($vars, EXTR_SKIP);

    // Template-Inhalt buffern
    ob_start();
    require __DIR__ . '/../templates/' . $template . '.php';
    $content = ob_get_clean();

    // Immer in Layout einbetten (Layout blendet Nav bei fehlendem $session aus)
    ob_start();
    require __DIR__ . '/../templates/layout.php';
    $html = ob_get_clean();

    $response = new Response($status);
    $response->getBody()->write($html);
    return $response;
}
