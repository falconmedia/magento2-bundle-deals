<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Discount;

/**
 * Request cache of the cart price rules that the module generated for discounted slots.
 */
class GeneratedRuleRegistry
{
    /**
     * @var array<int, array{rule_id: int, slot_id: int}>|null
     */
    private ?array $map = null;

    public function __construct(
        private readonly SalesRuleSynchronizer $synchronizer
    ) {
    }

    /**
     * Returns the bundle rule and slot of a generated cart price rule, if any.
     *
     * @return array{rule_id: int, slot_id: int}|null
     */
    public function get(int $salesRuleId): ?array
    {
        return $this->getMap()[$salesRuleId] ?? null;
    }

    /**
     * Returns the ids of all generated cart price rules.
     *
     * @return int[]
     */
    public function getSalesRuleIds(): array
    {
        return array_keys($this->getMap());
    }

    /**
     * Clears the cached map.
     */
    public function reset(): void
    {
        $this->map = null;
    }

    /**
     * Returns the generated cart price rule map, loading it once.
     *
     * @return array<int, array{rule_id: int, slot_id: int}>
     */
    private function getMap(): array
    {
        return $this->map ??= $this->synchronizer->getGeneratedRuleMap();
    }
}
