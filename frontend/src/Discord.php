<?php
declare(strict_types=1);

namespace App;

class Discord
{
    private const API_BASE = 'https://discord.com/api/v10';

    private function get(string $endpoint, string $authorization): array
    {
        $context = stream_context_create(['http' => [
            'method'        => 'GET',
            'header'        => "Authorization: $authorization\r\nUser-Agent: DailyNewsFeed/1.0",
            'timeout'       => 10,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents(self::API_BASE . $endpoint, false, $context);
        return $response ? (json_decode($response, true) ?? []) : [];
    }

    private function postForm(string $url, array $fields): array
    {
        $context = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\nUser-Agent: DailyNewsFeed/1.0",
            'content'       => http_build_query($fields),
            'timeout'       => 10,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
        return $response ? (json_decode($response, true) ?? []) : [];
    }

    public function exchangeCode(string $code): array
    {
        return $this->postForm('https://discord.com/api/oauth2/token', [
            'client_id'     => Config::require('DISCORD_CLIENT_ID'),
            'client_secret' => Config::require('DISCORD_CLIENT_SECRET'),
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'redirect_uri'  => Config::require('DISCORD_REDIRECT_URI'),
        ]);
    }

    public function refreshToken(string $refreshToken): array
    {
        return $this->postForm('https://discord.com/api/oauth2/token', [
            'client_id'     => Config::require('DISCORD_CLIENT_ID'),
            'client_secret' => Config::require('DISCORD_CLIENT_SECRET'),
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    public function getUser(string $accessToken): array
    {
        return $this->get('/users/@me', "Bearer $accessToken");
    }

    public function getUserGuilds(string $accessToken): array
    {
        return $this->get('/users/@me/guilds', "Bearer $accessToken");
    }

    public function getGuildMember(string $guildId, string $userId): ?array
    {
        $result = $this->get("/guilds/$guildId/members/$userId", 'Bot ' . Config::require('DISCORD_BOT_TOKEN'));
        return isset($result['user']) ? $result : null;
    }

    public function getGuildChannels(string $guildId): array
    {
        $result = $this->get("/guilds/$guildId/channels", 'Bot ' . Config::require('DISCORD_BOT_TOKEN'));
        if (!is_array($result) || isset($result['code'])) {
            return [];
        }
        // Nur Text-Kanäle (type 0 = GUILD_TEXT)
        return array_values(array_filter($result, fn($ch) => ($ch['type'] ?? -1) === 0));
    }

    public function buildAuthUrl(string $state): string
    {
        return 'https://discord.com/oauth2/authorize?' . http_build_query([
            'client_id'     => Config::require('DISCORD_CLIENT_ID'),
            'redirect_uri'  => Config::require('DISCORD_REDIRECT_URI'),
            'response_type' => 'code',
            'scope'         => 'identify guilds',
            'state'         => $state,
        ]);
    }
}
