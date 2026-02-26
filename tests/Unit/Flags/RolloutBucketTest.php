<?php

declare(strict_types=1);

namespace Zenmanage\Tests\Unit\Flags;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Zenmanage\Flags\RolloutBucket;

final class RolloutBucketTest extends TestCase
{
    /**
     * Cross-SDK test vectors from the specification.
     * These must produce identical results across all SDK implementations.
     *
     * @return array<string, array{salt: string, identifier: string, crc32bHex: string, unsigned: int, bucket: int, at50: bool, at25: bool, at10: bool}>
     */
    public static function vectorProvider(): array
    {
        return [
            'test-salt / user-0' => [
                'salt' => 'test-salt',
                'identifier' => 'user-0',
                'crc32bHex' => '83d08e62',
                'unsigned' => 2211483234,
                'bucket' => 34,
                'at50' => true,
                'at25' => false,
                'at10' => false,
            ],
            'test-salt / user-2' => [
                'salt' => 'test-salt',
                'identifier' => 'user-2',
                'crc32bHex' => '6ddeef4e',
                'unsigned' => 1843326798,
                'bucket' => 98,
                'at50' => false,
                'at25' => false,
                'at10' => false,
            ],
            'abc123 / ctx-alpha' => [
                'salt' => 'abc123',
                'identifier' => 'ctx-alpha',
                'crc32bHex' => 'b2b1cec6',
                'unsigned' => 2997997254,
                'bucket' => 54,
                'at50' => false,
                'at25' => false,
                'at10' => false,
            ],
            'abc123 / ctx-beta' => [
                'salt' => 'abc123',
                'identifier' => 'ctx-beta',
                'crc32bHex' => '037b773f',
                'unsigned' => 58423103,
                'bucket' => 3,
                'at50' => true,
                'at25' => true,
                'at10' => true,
            ],
            'rollout-salt / user-100' => [
                'salt' => 'rollout-salt',
                'identifier' => 'user-100',
                'crc32bHex' => '8ec865f5',
                'unsigned' => 2395497973,
                'bucket' => 73,
                'at50' => false,
                'at25' => false,
                'at10' => false,
            ],
            'rollout-salt / user-200' => [
                'salt' => 'rollout-salt',
                'identifier' => 'user-200',
                'crc32bHex' => '8c8edbac',
                'unsigned' => 2358172588,
                'bucket' => 88,
                'at50' => false,
                'at25' => false,
                'at10' => false,
            ],
            'fixed-salt / user-42' => [
                'salt' => 'fixed-salt',
                'identifier' => 'user-42',
                'crc32bHex' => '706dd0af',
                'unsigned' => 1886245039,
                'bucket' => 39,
                'at50' => true,
                'at25' => false,
                'at10' => false,
            ],
        ];
    }

    /**
     * @dataProvider vectorProvider
     */
    public function testCrc32bHexMatchesSpecVector(
        string $salt,
        string $identifier,
        string $crc32bHex,
        int $unsigned,
        int $bucket,
        bool $at50,
        bool $at25,
        bool $at10,
    ): void {
        $hash = hash('crc32b', $salt . ':' . $identifier);
        $this->assertSame($crc32bHex, $hash);
    }

    /**
     * @dataProvider vectorProvider
     */
    public function testUnsignedValueMatchesSpecVector(
        string $salt,
        string $identifier,
        string $crc32bHex,
        int $unsigned,
        int $bucket,
        bool $at50,
        bool $at25,
        bool $at10,
    ): void {
        $computed = hexdec(hash('crc32b', $salt . ':' . $identifier));
        $this->assertSame($unsigned, $computed);
    }

    /**
     * @dataProvider vectorProvider
     */
    public function testBucketValueMatchesSpecVector(
        string $salt,
        string $identifier,
        string $crc32bHex,
        int $unsigned,
        int $bucket,
        bool $at50,
        bool $at25,
        bool $at10,
    ): void {
        $computed = hexdec(hash('crc32b', $salt . ':' . $identifier));
        $this->assertSame($bucket, $computed % 100);
    }

    /**
     * @dataProvider vectorProvider
     */
    public function testIsInBucketAt50Percent(
        string $salt,
        string $identifier,
        string $crc32bHex,
        int $unsigned,
        int $bucket,
        bool $at50,
        bool $at25,
        bool $at10,
    ): void {
        $this->assertSame($at50, RolloutBucket::isInBucket($salt, $identifier, 50));
    }

    /**
     * @dataProvider vectorProvider
     */
    public function testIsInBucketAt25Percent(
        string $salt,
        string $identifier,
        string $crc32bHex,
        int $unsigned,
        int $bucket,
        bool $at50,
        bool $at25,
        bool $at10,
    ): void {
        $this->assertSame($at25, RolloutBucket::isInBucket($salt, $identifier, 25));
    }

    /**
     * @dataProvider vectorProvider
     */
    public function testIsInBucketAt10Percent(
        string $salt,
        string $identifier,
        string $crc32bHex,
        int $unsigned,
        int $bucket,
        bool $at50,
        bool $at25,
        bool $at10,
    ): void {
        $this->assertSame($at10, RolloutBucket::isInBucket($salt, $identifier, 10));
    }

    // --- Null identifier ---

    public function testNullIdentifierReturnsFalse(): void
    {
        $this->assertFalse(RolloutBucket::isInBucket('any-salt', null, 100));
    }

    // --- Percentage boundaries ---

    public function testZeroPercentAlwaysReturnsFalse(): void
    {
        // bucket 34 — not < 0
        $this->assertFalse(RolloutBucket::isInBucket('test-salt', 'user-0', 0));
        // bucket 3 — not < 0
        $this->assertFalse(RolloutBucket::isInBucket('abc123', 'ctx-beta', 0));
        // bucket 39 — not < 0
        $this->assertFalse(RolloutBucket::isInBucket('fixed-salt', 'user-42', 0));
    }

    public function testHundredPercentAlwaysReturnsTrue(): void
    {
        // All buckets [0,99] are < 100
        $this->assertTrue(RolloutBucket::isInBucket('test-salt', 'user-0', 100));
        $this->assertTrue(RolloutBucket::isInBucket('test-salt', 'user-2', 100));
        $this->assertTrue(RolloutBucket::isInBucket('abc123', 'ctx-alpha', 100));
    }

    public function testHundredPercentWithNullIdentifierReturnsFalse(): void
    {
        $this->assertFalse(RolloutBucket::isInBucket('any-salt', null, 100));
    }

    public function testNegativePercentageThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Percentage must be between 0 and 100');
        RolloutBucket::isInBucket('salt', 'id', -1);
    }

    public function testPercentageAbove100ThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Percentage must be between 0 and 100');
        RolloutBucket::isInBucket('salt', 'id', 101);
    }

    public function testPercentageZeroDoesNotThrow(): void
    {
        // Should not throw
        RolloutBucket::isInBucket('salt', 'id', 0);
        $this->assertTrue(true); // Assertion to avoid risky test
    }

    public function testPercentageHundredDoesNotThrow(): void
    {
        // Should not throw
        RolloutBucket::isInBucket('salt', 'id', 100);
        $this->assertTrue(true);
    }

    // --- Deterministic bucketing ---

    public function testSameInputsProduceSameResult(): void
    {
        $salt = 'deterministic-test';
        $id = 'user-abc';
        $pct = 50;

        $result1 = RolloutBucket::isInBucket($salt, $id, $pct);
        $result2 = RolloutBucket::isInBucket($salt, $id, $pct);
        $result3 = RolloutBucket::isInBucket($salt, $id, $pct);

        $this->assertSame($result1, $result2);
        $this->assertSame($result2, $result3);
    }

    public function testDifferentSaltsProduceDifferentBuckets(): void
    {
        $id = 'ctx-beta'; // bucket 3 with salt "abc123"
        $this->assertTrue(RolloutBucket::isInBucket('abc123', $id, 5));
        // Different salt should give a different bucket
        $this->assertNotSame(
            RolloutBucket::isInBucket('abc123', $id, 5),
            RolloutBucket::isInBucket('test-salt', $id, 5),
        );
    }

    // --- Monotonic rollout expansion ---

    public function testContextRemainsInBucketAsPercentageIncreases(): void
    {
        // abc123 + ctx-beta = bucket 3
        // Once in at 4%, should remain in at all higher percentages
        $this->assertTrue(RolloutBucket::isInBucket('abc123', 'ctx-beta', 4));
        $this->assertTrue(RolloutBucket::isInBucket('abc123', 'ctx-beta', 10));
        $this->assertTrue(RolloutBucket::isInBucket('abc123', 'ctx-beta', 25));
        $this->assertTrue(RolloutBucket::isInBucket('abc123', 'ctx-beta', 50));
        $this->assertTrue(RolloutBucket::isInBucket('abc123', 'ctx-beta', 100));
    }

    public function testContextNeverRemovedFromBucketWhenPercentageGrows(): void
    {
        // test-salt + user-0 = bucket 34
        $this->assertFalse(RolloutBucket::isInBucket('test-salt', 'user-0', 34)); // 34 NOT < 34
        $this->assertTrue(RolloutBucket::isInBucket('test-salt', 'user-0', 35)); // 34 < 35
        $this->assertTrue(RolloutBucket::isInBucket('test-salt', 'user-0', 50));
        $this->assertTrue(RolloutBucket::isInBucket('test-salt', 'user-0', 100));
    }

    // --- Colon delimiter ---

    public function testColonDelimiterPreventsAmbiguousConcatenation(): void
    {
        // Without a delimiter, "ab" + "c" and "a" + "bc" would collide
        $hash1 = hexdec(hash('crc32b', 'ab:c'));
        $hash2 = hexdec(hash('crc32b', 'a:bc'));
        $this->assertNotSame($hash1, $hash2);
    }

    // --- Distribution ---

    public function testRoughDistributionAt50Percent(): void
    {
        $salt = 'distribution-test-salt';
        $total = 10000;
        $inBucket = 0;

        for ($i = 0; $i < $total; $i++) {
            if (RolloutBucket::isInBucket($salt, "user-{$i}", 50)) {
                $inBucket++;
            }
        }

        $ratio = $inBucket / $total;
        $this->assertGreaterThan(0.45, $ratio);
        $this->assertLessThan(0.55, $ratio);
    }

    public function testRoughDistributionAt25Percent(): void
    {
        $salt = 'dist-25-salt';
        $total = 10000;
        $inBucket = 0;

        for ($i = 0; $i < $total; $i++) {
            if (RolloutBucket::isInBucket($salt, "user-{$i}", 25)) {
                $inBucket++;
            }
        }

        $ratio = $inBucket / $total;
        $this->assertGreaterThan(0.20, $ratio);
        $this->assertLessThan(0.30, $ratio);
    }

    public function testRoughDistributionAt10Percent(): void
    {
        $salt = 'dist-10-salt';
        $total = 10000;
        $inBucket = 0;

        for ($i = 0; $i < $total; $i++) {
            if (RolloutBucket::isInBucket($salt, "user-{$i}", 10)) {
                $inBucket++;
            }
        }

        $ratio = $inBucket / $total;
        $this->assertGreaterThan(0.07, $ratio);
        $this->assertLessThan(0.13, $ratio);
    }
}
