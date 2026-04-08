<?php
declare(strict_types=1);

namespace App\Tests;

use App\Actions\Api\AdminLogsAction;
use PHPUnit\Framework\TestCase;

class AdminLogsTest extends TestCase
{
    public function testParseValidClefLine(): void
    {
        $line = '{"@t":"2026-04-08T18:00:00.000Z","@l":"Information","@mt":"Bot gestartet"}';
        $result = AdminLogsAction::parseJsonLines([$line]);

        $this->assertCount(1, $result);
        $this->assertSame('2026-04-08T18:00:00.000Z', $result[0]['timestamp']);
        $this->assertSame('Information', $result[0]['level']);
        $this->assertSame('Bot gestartet', $result[0]['message']);
        $this->assertArrayNotHasKey('raw', $result[0]);
    }

    public function testParsePrefersRenderedMessage(): void
    {
        // @m (rendered) takes priority over @mt (template)
        $line = '{"@t":"2026-04-08T18:00:00.000Z","@l":"Warning","@mt":"Feed {Url}","@m":"Feed https://example.com"}';
        $result = AdminLogsAction::parseJsonLines([$line]);

        $this->assertSame('Feed https://example.com', $result[0]['message']);
    }

    public function testParseInvalidJsonFallsBackToRaw(): void
    {
        $line = 'not valid json at all';
        $result = AdminLogsAction::parseJsonLines([$line]);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('raw', $result[0]);
        $this->assertSame('not valid json at all', $result[0]['raw']);
    }

    public function testParseMixedLines(): void
    {
        $lines = [
            '{"@t":"2026-04-08T18:00:00.000Z","@l":"Error","@mt":"Fehler"}',
            'plain text startup line',
        ];
        $result = AdminLogsAction::parseJsonLines($lines);

        $this->assertCount(2, $result);
        $this->assertSame('Error', $result[0]['level']);
        $this->assertArrayHasKey('raw', $result[1]);
    }

    public function testParseEmptyArray(): void
    {
        $this->assertSame([], AdminLogsAction::parseJsonLines([]));
    }

    public function testParseClefWithoutLevel(): void
    {
        // Serilog omits @l on Information level — parser must default it
        $line = '{"@t":"2026-04-08T18:00:00.000Z","@mt":"Bot gestartet"}';
        $result = AdminLogsAction::parseJsonLines([$line]);

        $this->assertSame('Information', $result[0]['level']);
    }
}
