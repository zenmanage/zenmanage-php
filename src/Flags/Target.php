<?php

declare(strict_types=1);

namespace Zenmanage\Flags;

use JsonSerializable;
use Zenmanage\Rules\RuleValue;

/**
 * Represents a flag target (the default value when no rules match).
 */
final class Target implements JsonSerializable
{
    public function __construct(
        private readonly string $version,
        private readonly ?string $expiredAt,
        private readonly ?string $publishedAt,
        private readonly ?string $scheduledAt,
        private readonly RuleValue $value,
    ) {
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getExpiredAt(): ?string
    {
        return $this->expiredAt;
    }

    public function getPublishedAt(): ?string
    {
        return $this->publishedAt;
    }

    public function getScheduledAt(): ?string
    {
        return $this->scheduledAt;
    }

    public function getValue(): RuleValue
    {
        return $this->value;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $version = isset($data['version']) && is_string($data['version']) ? $data['version'] : '';

        return new self(
            version: $version,
            expiredAt: isset($data['expired_at']) && is_string($data['expired_at']) ? $data['expired_at'] : null,
            publishedAt: isset($data['published_at']) && is_string($data['published_at']) ? $data['published_at'] : null,
            scheduledAt: isset($data['scheduled_at']) && is_string($data['scheduled_at']) ? $data['scheduled_at'] : null,
            value: RuleValue::fromArray(is_array($data['value'] ?? null) ? $data['value'] : []),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'version' => $this->version,
            'expired_at' => $this->expiredAt,
            'published_at' => $this->publishedAt,
            'scheduled_at' => $this->scheduledAt,
            'value' => $this->value->jsonSerialize(),
        ];
    }
}
