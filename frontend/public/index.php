<?php
declare(strict_types=1);

use App\Actions\Auth\CallbackAction;
use App\Actions\Api\BotCommandAction;
use App\Actions\Api\CategoryDeleteAction;
use App\Actions\Api\CategoryOrderAction;
use App\Actions\Api\CategorySaveAction;
use App\Actions\Api\CategoryUpdateAction;
use App\Actions\Api\ChannelDeleteAction;
use App\Actions\Api\ChannelRegisterAction;
use App\Actions\Api\FeedDeleteAction;
use App\Actions\Api\FeedSaveAction;
use App\Actions\Api\FeedTestAction;
use App\Actions\Api\FeedUpdateAction;
use App\Actions\Api\GuildChannelsAction;
use App\Actions\Api\GuildListAction;
use App\Actions\AdminAction;
use App\Actions\ChannelAction;
use App\Actions\DashboardAction;
use App\Actions\LoginAction;
use App\Actions\LogoutAction;
use App\Middleware\AuthMiddleware;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

if (!isset($_ENV['DB_HOST'], $_ENV['DISCORD_CLIENT_ID'], $_ENV['TOKEN_ENCRYPTION_KEY'])) {
    http_response_code(503);
    echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><title>Setup erforderlich</title>'
        . '<style>body{font-family:sans-serif;max-width:600px;margin:80px auto;padding:0 20px}'
        . 'h1{color:#e53e3e}code{background:#f4f4f4;padding:2px 6px;border-radius:3px}</style></head>'
        . '<body><h1>Setup erforderlich</h1>'
        . '<p>Die Datei <code>.env</code> fehlt oder ist unvollständig.</p>'
        . '<p>Kopiere <code>.env.example</code> nach <code>.env</code> und trage alle Werte ein:</p>'
        . '<pre>cp .env.example .env&#10;nano .env</pre>'
        . '<p>Danach Apache neu starten: <code>sudo systemctl reload apache2</code></p>'
        . '</body></html>';
    exit;
}

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(require __DIR__ . '/../src/container.php');
$container = $containerBuilder->build();

AppFactory::setContainer($container);
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();
$app->addErrorMiddleware((bool)($_ENV['APP_DEBUG'] ?? false), true, true);

// Routen ohne Auth
$app->get('/', LoginAction::class);
$app->get('/auth/callback', CallbackAction::class);
$app->get('/logout', LogoutAction::class);
// DevLoginAction ist gitignored — nur lokal verfügbar
if (class_exists(\App\Actions\DevLoginAction::class)) {
    $app->get('/dev-login', \App\Actions\DevLoginAction::class);
}

// Routen mit Auth-Middleware
$app->group('', function (\Slim\Routing\RouteCollectorProxy $group) {
    $group->get('/dashboard', DashboardAction::class);
    $group->get('/admin', AdminAction::class);
    $group->get('/channel/{channel_id}', ChannelAction::class);
    $group->post('/api/feed/test', FeedTestAction::class);
    $group->post('/api/feed/save', FeedSaveAction::class);
    $group->put('/api/feed/{id}', FeedUpdateAction::class);
    $group->delete('/api/feed/{id}', FeedDeleteAction::class);
    $group->post('/api/category/save', CategorySaveAction::class);
    $group->post('/api/category/order', CategoryOrderAction::class);
    $group->put('/api/category/{id}', CategoryUpdateAction::class);
    $group->delete('/api/category/{id}', CategoryDeleteAction::class);
    $group->post('/api/bot/command', BotCommandAction::class);
    $group->get('/api/guilds', GuildListAction::class);
    $group->get('/api/guilds/{guild_id}/channels', GuildChannelsAction::class);
    $group->post('/api/channel/register', ChannelRegisterAction::class);
    $group->delete('/api/channel/{channel_id}', ChannelDeleteAction::class);
})->add(AuthMiddleware::class);

$app->run();
