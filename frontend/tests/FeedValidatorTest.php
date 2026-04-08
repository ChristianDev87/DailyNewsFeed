<?php
declare(strict_types=1);

namespace App\Tests;

use App\FeedValidator;
use PHPUnit\Framework\TestCase;

class FeedValidatorTest extends TestCase
{
    private FeedValidator $v;

    protected function setUp(): void
    {
        $this->v = new FeedValidator();
    }

    public function testRejectsNonUrl(): void
    {
        $result = $this->v->validateUrl('not-a-url');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Ungültige URL', $result['error']);
    }

    public function testRejectsFtpScheme(): void
    {
        $result = $this->v->validateUrl('ftp://example.com/feed.xml');
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('HTTP/HTTPS', $result['error']);
    }

    public function testRejectsUnresolvableHost(): void
    {
        $result = $this->v->validateUrl('https://this-host-does-not-exist-xyz-12345.invalid/feed.xml');
        $this->assertFalse($result['valid']);
    }

    public function testIsPrivateIpLoopback(): void
    {
        $this->assertTrue(FeedValidator::isPrivateIp('127.0.0.1'));
        $this->assertTrue(FeedValidator::isPrivateIp('127.0.0.50'));
    }

    public function testIsPrivateIpClassA(): void
    {
        $this->assertTrue(FeedValidator::isPrivateIp('10.0.0.1'));
        $this->assertTrue(FeedValidator::isPrivateIp('10.255.255.255'));
    }

    public function testIsPrivateIpClassB(): void
    {
        $this->assertTrue(FeedValidator::isPrivateIp('172.16.0.1'));
        $this->assertTrue(FeedValidator::isPrivateIp('172.31.255.255'));
    }

    public function testIsPrivateIpClassC(): void
    {
        $this->assertTrue(FeedValidator::isPrivateIp('192.168.0.1'));
        $this->assertTrue(FeedValidator::isPrivateIp('192.168.255.255'));
    }

    public function testIsPrivateIpLinkLocal(): void
    {
        $this->assertTrue(FeedValidator::isPrivateIp('169.254.1.1'));
    }

    public function testIsPrivateIpv6Loopback(): void
    {
        $this->assertTrue(FeedValidator::isPrivateIp('::1'));
    }

    public function testIsNotPrivatePublicIp(): void
    {
        $this->assertFalse(FeedValidator::isPrivateIp('8.8.8.8'));
        $this->assertFalse(FeedValidator::isPrivateIp('1.1.1.1'));
        $this->assertFalse(FeedValidator::isPrivateIp('93.184.216.34'));
    }
}
