<?php
declare(strict_types=1);

use App\Auth;
use App\Database;
use App\Discord;
use App\FeedValidator;
use App\Middleware\AuthMiddleware;
use function DI\autowire;

return [
    Database::class      => autowire(Database::class),
    Auth::class          => autowire(Auth::class),
    Discord::class       => autowire(Discord::class),
    FeedValidator::class => autowire(FeedValidator::class),
    AuthMiddleware::class => autowire(AuthMiddleware::class),
];
