<?php

declare(strict_types=1);

namespace Zenmanage\Flags;

use InvalidArgumentException;

/**
 * Percentage rollout bucketing algorithm.
 *
 * Uses CRC32B (IEEE polynomial) to deterministically assign contexts to buckets.
 * The same (salt, contextIdentifier) pair always produces the same result across
 * all SDKs and the API.
 */
final class RolloutBucket
{
    /**
     * Determine whether a context identifier falls within a rollout bucket.
     *
     * The algorithm is deterministic: the same salt + identifier always produces
     * the same result. Increasing the percentage only adds new contexts to the
     * bucket — it never removes existing ones (monotonic expansion).
     *
     * @param string      $salt              Random string unique to the rollout, used as the hash seed
     * @param string|null $contextIdentifier The context identifier (e.g., user ID). Null means not in bucket.
     * @param int         $percentage        The rollout percentage (0–100)
     *
     * @throws InvalidArgumentException If percentage is out of range
     */
    public static function isInBucket(string $salt, ?string $contextIdentifier, int $percentage): bool
    {
        if ($percentage < 0 || $percentage > 100) {
            throw new InvalidArgumentException('Percentage must be between 0 and 100.');
        }

        if ($contextIdentifier === null) {
            return false;
        }

        $unsigned = hexdec(hash('crc32b', $salt . ':' . $contextIdentifier));

        return ($unsigned % 100) < $percentage;
    }
}
