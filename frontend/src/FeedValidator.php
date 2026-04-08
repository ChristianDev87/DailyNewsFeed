<?php
declare(strict_types=1);

namespace App;

class FeedValidator
{
    public function validateUrl(string $url): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'error' => 'Ungültige URL'];
        }

        $parts  = parse_url($url);
        $scheme = $parts['scheme'] ?? '';
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['valid' => false, 'error' => 'Nur HTTP/HTTPS erlaubt'];
        }

        $host = $parts['host'] ?? '';
        if (!$host) {
            return ['valid' => false, 'error' => 'Ungültiger Host'];
        }

        $ip = gethostbyname($host);
        if ($ip === $host) {
            return ['valid' => false, 'error' => 'Host nicht auflösbar'];
        }
        if (self::isPrivateIp($ip)) {
            return ['valid' => false, 'error' => 'Private IP-Adressen sind nicht erlaubt'];
        }

        $context = stream_context_create(['http' => [
            'timeout'       => 5,
            'method'        => 'GET',
            'header'        => 'User-Agent: DailyNewsFeed/1.0',
            'ignore_errors' => true,
        ]]);

        $content = @file_get_contents($url, false, $context);
        if (!$content) {
            return ['valid' => false, 'error' => 'Feed nicht erreichbar'];
        }

        if (!self::isValidFeed($content)) {
            return ['valid' => false, 'error' => 'Kein gültiger RSS/Atom-Feed'];
        }

        return ['valid' => true, 'error' => ''];
    }

    public static function isPrivateIp(string $ip): bool
    {
        if ($ip === '::1') {
            return true;
        }

        static $ranges = null;
        if ($ranges === null) {
            $ranges = array_map(
                fn(array $r) => [ip2long($r[0]), ip2long($r[1])],
                [
                    ['127.0.0.0',   '127.255.255.255'],
                    ['10.0.0.0',    '10.255.255.255'],
                    ['172.16.0.0',  '172.31.255.255'],
                    ['192.168.0.0', '192.168.255.255'],
                    ['169.254.0.0', '169.254.255.255'],
                ]
            );
        }

        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false;
        }

        foreach ($ranges as [$start, $end]) {
            if ($ipLong >= $start && $ipLong <= $end) {
                return true;
            }
        }

        return false;
    }

    private static function isValidFeed(string $content): bool
    {
        return str_contains($content, '<rss')
            || str_contains($content, '<feed')
            || str_contains($content, '<rdf:RDF');
    }
}
