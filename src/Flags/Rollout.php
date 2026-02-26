<?php

declare(strict_types=1);

namespace Zenmanage\Flags;

use JsonSerializable;
use Zenmanage\Rules\Rule;

/**
 * Represents a percentage rollout configuration on a flag.
 *
 * Present only when a rollout is active. Contains a separate target/rules pair
 * and the bucketing parameters (salt, percentage).
 */
final class Rollout implements JsonSerializable
{
    /**
     * @param Rule[] $rules
     */
    public function __construct(
        private readonly Target $target,
        private readonly array $rules,
        private readonly int $percentage,
        private readonly string $salt,
        private readonly string $status,
    ) {
    }

    public function getTarget(): Target
    {
        return $this->target;
    }

    /**
     * @return Rule[]
     */
    public function getRules(): array
    {
        return $this->rules;
    }

    public function getPercentage(): int
    {
        return $this->percentage;
    }

    public function getSalt(): string
    {
        return $this->salt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rules = [];

        if (isset($data['rules']) && is_array($data['rules'])) {
            foreach ($data['rules'] as $ruleData) {
                $rules[] = Rule::fromArray($ruleData);
            }
        }

        $percentage = isset($data['percentage']) && is_int($data['percentage']) ? $data['percentage'] : 0;
        $salt = isset($data['salt']) && is_string($data['salt']) ? $data['salt'] : '';
        $status = isset($data['status']) && is_string($data['status']) ? $data['status'] : 'active';

        return new self(
            target: Target::fromArray(is_array($data['target'] ?? null) ? $data['target'] : []),
            rules: $rules,
            percentage: $percentage,
            salt: $salt,
            status: $status,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'target' => $this->target->jsonSerialize(),
            'rules' => array_map(fn ($r) => $r->jsonSerialize(), $this->rules),
            'percentage' => $this->percentage,
            'salt' => $this->salt,
            'status' => $this->status,
        ];
    }
}
