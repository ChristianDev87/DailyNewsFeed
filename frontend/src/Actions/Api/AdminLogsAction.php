<?php
declare(strict_types=1);

namespace App\Actions\Api;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Response;

class AdminLogsAction
{
    private const LOG_DIRS = [
        'bot'      => '/var/log/dailynews/bot',
        'watchdog' => '/var/log/dailynews/watchdog',
        'frontend' => '/var/log/dailynews/frontend',
    ];

    private const ALLOWED_LINES = [50, 100, 200, 500];
    private const ALLOWED_FILES = ['access', 'error'];

    public function __invoke(ServerRequestInterface $request, Response $response): ResponseInterface
    {
        if (!$request->getAttribute('is_superadmin')) {
            return $this->json($response, ['success' => false, 'error' => 'Kein Zugriff'], 403);
        }

        $params = $request->getQueryParams();
        $source = $params['source'] ?? 'bot';
        $lines  = (int)($params['lines'] ?? 200);
        $file   = $params['file'] ?? 'access';

        if (!array_key_exists($source, self::LOG_DIRS)) {
            return $this->json($response, ['success' => false, 'error' => 'Ungültige Quelle'], 400);
        }

        $lines = in_array($lines, self::ALLOWED_LINES, true) ? $lines : 200;

        $logFile = $this->resolveLogFile($source, $file);
        if ($logFile === null || !is_readable($logFile)) {
            return $this->json($response, ['lines' => [], 'file' => '']);
        }

        $rawLines = $this->tailFile($logFile, $lines);
        $parsed   = ($source === 'frontend')
            ? array_map(fn(string $l) => ['raw' => $l], $rawLines)
            : self::parseJsonLines($rawLines);

        return $this->json($response, ['lines' => $parsed, 'file' => basename($logFile)]);
    }

    private function resolveLogFile(string $source, string $nginxFile): ?string
    {
        $dir = self::LOG_DIRS[$source];

        if ($source === 'watchdog') {
            $path = $dir . '/watchdog.log';
            return file_exists($path) ? $path : null;
        }

        if ($source === 'frontend') {
            $f    = in_array($nginxFile, self::ALLOWED_FILES, true) ? $nginxFile : 'access';
            $path = $dir . '/' . $f . '.log';
            return file_exists($path) ? $path : null;
        }

        // bot: find newest bot-YYYYMMDD.log
        if (!is_dir($dir)) {
            return null;
        }
        $files = glob($dir . '/bot-*.log') ?: [];
        if (empty($files)) {
            return null;
        }
        usort($files, fn(string $a, string $b) => filemtime($b) - filemtime($a));
        return $files[0];
    }

    private function tailFile(string $path, int $n): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }
        return array_slice($lines, -$n);
    }

    public static function parseJsonLines(array $rawLines): array
    {
        return array_map(function (string $line): array {
            $data = json_decode($line, true);
            if (!is_array($data)) {
                return ['raw' => $line];
            }
            return [
                'timestamp' => $data['@t'] ?? '',
                'level'     => $data['@l'] ?? 'Information',
                'message'   => $data['@m'] ?? $data['@mt'] ?? $line,
            ];
        }, $rawLines);
    }

    private function json(Response $response, array $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
