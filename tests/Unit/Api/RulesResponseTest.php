<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Api;

use PHPUnit\Framework\TestCase;
use Zenmanage\Api\Response\RulesResponse;
use Zenmanage\Exception\InvalidRulesException;

final class RulesResponseTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function loadFixture(): array
    {
        $fixturePath = __DIR__ . '/../../Fixtures/rules.json';
        $contents = file_get_contents($fixturePath) ?: '[]';

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $decoded = [];
        }

        return $decoded;
    }

    public function testParsesFlagsAndRetrievesByKey(): void
    {
        $response = RulesResponse::fromArray($this->loadFixture());

        $this->assertSame('2026-01-15', $response->getVersion());
        $flag = $response->getFlagByKey('test-feature');
        $this->assertNotNull($flag);
        $this->assertSame('test-feature', $flag->getKey());
        $this->assertNull($response->getFlagByKey('missing-flag'));
    }

    public function testThrowsWhenVersionMissing(): void
    {
        $this->expectException(InvalidRulesException::class);
        $this->expectExceptionMessage('Rules response missing "version" field');

        RulesResponse::fromArray(['flags' => []]);
    }

    public function testThrowsWhenFlagsMissing(): void
    {
        $this->expectException(InvalidRulesException::class);
        $this->expectExceptionMessage('Rules response missing or invalid "flags" field');

        RulesResponse::fromArray(['version' => '1.0.0']);
    }
}
