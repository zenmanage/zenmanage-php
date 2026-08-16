<?php

declare(strict_types=1);

namespace Zenmanage\Flags;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Zenmanage\Api\ApiClientInterface;
use Zenmanage\Cache\CacheInterface;
use Zenmanage\Exception\EvaluationException;
use Zenmanage\Flags\Context\Context;
use Zenmanage\Rules\RuleEngineInterface;

/**
 * Main flag manager that orchestrates fetching, caching, and evaluating flags.
 */
final class FlagManager implements FlagManagerInterface
{
    private const CACHE_KEY = 'zenmanage_rules';

    /** @var Flag[]|null */
    private ?array $flags = null;

    private Context $context;

    private DefaultsCollection $defaults;

    public function __construct(
        private readonly ApiClientInterface $apiClient,
        private readonly CacheInterface $cache,
        private readonly RuleEngineInterface $ruleEngine,
        private readonly int $cacheTtl,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
        $this->context = new Context('anonymous');
        $this->defaults = new DefaultsCollection();
    }

    public function all(): array
    {
        try {
            $this->ensureRulesLoaded();
            $flags = $this->flags ?? [];
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to load rules, falling back to configured defaults', [
                'error' => $e->getMessage(),
            ]);

            $flags = [];
        }

        $evaluated = array_map(fn ($flag) => $this->evaluateFlag($flag), $flags);

        $loadedKeys = array_map(fn (Flag $flag): string => $flag->getKey(), $evaluated);

        foreach ($this->defaults->all() as $key => $value) {
            if (in_array($key, $loadedKeys, true) === false) {
                $evaluated[] = $this->createFlagFromDefault($key, $value);
            }
        }

        return $evaluated;
    }

    public function single(string $key, mixed $default = null): Flag
    {
        $this->ensureRulesLoaded();

        foreach ($this->flags ?? [] as $flag) {
            if ($flag->getKey() === $key) {
                // Report usage for this flag, including the effective default (inline
                // parameter, falling back to a DefaultsCollection entry) so it's recorded
                // even when the flag was found and evaluated normally
                $this->reportUsage($key, $this->getUsageContext(), $this->resolveEffectiveDefault($key, $default));

                return $this->evaluateFlag($flag);
            }
        }

        // Flag not found: fall back to the effective default (inline parameter,
        // prioritized over a DefaultsCollection entry), if one exists
        $effectiveDefault = $this->resolveEffectiveDefault($key, $default);

        if ($effectiveDefault !== null) {
            $flagFromDefault = $this->createFlagFromDefault($key, $effectiveDefault);
            // Report usage even for default values
            $this->reportUsage($key, $this->getUsageContext(), $effectiveDefault);

            return $flagFromDefault;
        }

        throw new EvaluationException("Flag not found: {$key}");
    }

    public function withContext(Context $context): self
    {
        $clone = clone $this;
        $clone->context = $context;

        return $clone;
    }

    public function withDefaults(DefaultsCollection $defaults): self
    {
        $clone = clone $this;
        $clone->defaults = $defaults;

        return $clone;
    }

    public function reportUsage(string $key, ?Context $context = null, mixed $defaultValue = null): void
    {
        $this->apiClient->reportUsage($key, $context, $defaultValue);
    }

    /**
     * Resolve the default value that would be used if this flag fell back,
     * prioritizing the inline parameter over a DefaultsCollection entry.
     */
    private function resolveEffectiveDefault(string $key, mixed $default): mixed
    {
        if ($default !== null) {
            return $default;
        }

        return $this->defaults->has($key) === true ? $this->defaults->get($key) : null;
    }

    private function getUsageContext(): ?Context
    {
        if (
            $this->context->getType() === 'anonymous'
            && $this->context->getName() === null
            && $this->context->getIdentifier() === null
            && $this->context->getAttributes() === []
        ) {
            return null;
        }

        return $this->context;
    }

    public function refreshRules(): void
    {
        $this->logger->info('Refreshing rules from API');

        $this->loadRulesFromApi();
    }

    /**
     * Ensure rules are loaded (from cache or API).
     */
    private function ensureRulesLoaded(): void
    {
        if ($this->flags !== null) {
            return;
        }

        // Try to load from cache first
        $cached = $this->cache->get(self::CACHE_KEY);

        if ($cached !== null) {
            $this->logger->debug('Loading rules from cache');

            try {
                $data = json_decode($cached, true);

                if (is_array($data) === true) {
                    $this->flags = $this->parseFlags($data);

                    return;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to parse cached rules', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Load from API
        $this->loadRulesFromApi();
    }

    /**
     * Load rules from the API and cache them.
     */
    private function loadRulesFromApi(): void
    {
        $this->logger->info('Fetching rules from API');

        $response = $this->apiClient->getRules();
        $this->flags = $response->getFlags();

        // Cache the rules
        $data = [
            'version' => $response->getVersion(),
            'flags' => array_map(fn ($f) => $f->jsonSerialize(), $this->flags),
        ];

        $this->cache->set(self::CACHE_KEY, json_encode($data) ?: '', $this->cacheTtl);

        $this->logger->info('Rules cached successfully', [
            'flag_count' => count($this->flags),
            'ttl' => $this->cacheTtl,
        ]);
    }

    /**
     * Parse flags from cached data.
     *
     * @param array<string, mixed> $data
     *
     * @return Flag[]
     */
    private function parseFlags(array $data): array
    {
        $flags = [];

        if (isset($data['flags']) === true && is_array($data['flags']) === true) {
            foreach ($data['flags'] as $flagData) {
                try {
                    $flags[] = Flag::fromArray($flagData);
                } catch (\Exception $e) {
                    $this->logger->warning('Failed to parse flag', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $flags;
    }

    /**
     * Create a flag from a default value.
     */
    private function createFlagFromDefault(string $key, mixed $value): Flag
    {
        // Determine type from value
        $type = match (true) {
            is_bool($value) => 'boolean',
            is_int($value) || is_float($value) => 'number',
            is_string($value) => 'string',
            default => 'string',
        };

        // Wrap value in appropriate format
        $wrappedValue = match ($type) {
            'boolean' => ['boolean' => $value],
            'number' => ['number' => $value],
            default => ['string' => $value],
        };

        $ruleValue = new \Zenmanage\Rules\RuleValue(
            version: 'default',
            value: $wrappedValue,
        );

        $target = new Target(
            version: 'default',
            expiredAt: null,
            publishedAt: null,
            scheduledAt: null,
            value: $ruleValue,
        );

        return new Flag(
            version: 'default',
            type: $type,
            key: $key,
            name: $key,
            target: $target,
            rules: [],
        );
    }

    /**
     * Evaluate a flag with the current context.
     *
     * When a rollout is active, the SDK determines which target/rules pair to use
     * by bucketing the context identifier against the rollout percentage.
     */
    private function evaluateFlag(Flag $flag): Flag
    {
        $rollout = $flag->getRollout();
        $target = $flag->getTarget();
        $rules = $flag->getRules();

        if ($rollout !== null) {
            // Rollout is active — determine which target to use via bucketing
            $contextIdentifier = $this->context->getIdentifier();
            $inBucket = RolloutBucket::isInBucket(
                $rollout->getSalt(),
                $contextIdentifier,
                $rollout->getPercentage(),
            );

            if ($inBucket === true) {
                // Context is in the rollout bucket — use rollout target & rules
                $target = $rollout->getTarget();
                $rules = $rollout->getRules();
            }
            // Otherwise keep the fallback target & rules
        }

        // Build a temporary Flag object with the selected target/rules for rule evaluation
        $evaluationFlag = new Flag(
            version: $flag->getVersion(),
            type: $flag->getType(),
            key: $flag->getKey(),
            name: $flag->getName(),
            target: $target,
            rules: $rules,
        );

        $evaluatedValue = $this->ruleEngine->evaluate($evaluationFlag, $this->context);

        // Create a new flag with the evaluated value
        $newTarget = new Target(
            version: $target->getVersion(),
            expiredAt: $target->getExpiredAt(),
            publishedAt: $target->getPublishedAt(),
            scheduledAt: $target->getScheduledAt(),
            value: new \Zenmanage\Rules\RuleValue(
                version: $target->getValue()->getVersion(),
                value: $evaluatedValue,
            ),
        );

        return new Flag(
            version: $flag->getVersion(),
            type: $flag->getType(),
            key: $flag->getKey(),
            name: $flag->getName(),
            target: $newTarget,
            rules: $rules,
        );
    }
}
