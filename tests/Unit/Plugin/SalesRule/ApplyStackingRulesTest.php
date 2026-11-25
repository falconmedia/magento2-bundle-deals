<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Test\Unit\Plugin\SalesRule;

use FalconMedia\BundleDeals\Model\Discount\GeneratedRuleRegistry;
use FalconMedia\BundleDeals\Model\Rule as BundleRule;
use FalconMedia\BundleDeals\Model\Set\ActiveRuleProvider;
use FalconMedia\BundleDeals\Model\Set\CartSetResolver;
use FalconMedia\BundleDeals\Plugin\SalesRule\ApplyStackingRules;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use Magento\SalesRule\Model\Rule;
use Magento\SalesRule\Model\RulesApplier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApplyStackingRulesTest extends TestCase
{
    private CartSetResolver&MockObject $resolver;
    private ActiveRuleProvider&MockObject $ruleProvider;
    private GeneratedRuleRegistry&MockObject $registry;
    private Item&MockObject $item;

    protected function setUp(): void
    {
        $this->resolver = $this->createMock(CartSetResolver::class);
        $this->ruleProvider = $this->createMock(ActiveRuleProvider::class);
        $this->registry = $this->createMock(GeneratedRuleRegistry::class);
        $this->registry->method('get')->willReturnCallback(
            static fn (int $id): ?array => $id === 100 ? ['rule_id' => 1, 'slot_id' => 2] : null
        );
        $quote = $this->createMock(Quote::class);
        $quote->method('getStoreId')->willReturn(3);
        $this->item = $this->createMock(Item::class);
        $this->item->method('getQuote')->willReturn($quote);
    }

    public function testItemOutsideCompleteSetKeepsAllRules(): void
    {
        $this->resolver->method('getRuleIdsForItem')->willReturn([]);

        self::assertNull($this->plugin()->beforeApplyRules($this->applier(), $this->item, [$this->rule(5, 0)], false));
    }

    public function testRuleNotValidOnBundleItemsIsRemoved(): void
    {
        $this->resolver->method('getRuleIdsForItem')->willReturn([1]);
        $this->ruleProvider->method('getRule')->willReturn($this->bundleRule(false));
        $rules = [$this->rule(100, 0), $this->rule(5, 0), $this->rule(6, 1)];

        $result = $this->plugin()->beforeApplyRules($this->applier(), $this->item, $rules, false);

        self::assertSame([100, 6], $this->ids($result[1]));
    }

    public function testExclusiveBundleKeepsOnlyGeneratedRules(): void
    {
        $this->resolver->method('getRuleIdsForItem')->willReturn([1]);
        $this->ruleProvider->method('getRule')->willReturn($this->bundleRule(true));
        $rules = [$this->rule(100, 1), $this->rule(6, 1)];

        $result = $this->plugin()->beforeApplyRules($this->applier(), $this->item, $rules, false);

        self::assertSame([100], $this->ids($result[1]));
    }

    private function plugin(): ApplyStackingRules
    {
        return new ApplyStackingRules($this->resolver, $this->ruleProvider, $this->registry);
    }

    private function applier(): RulesApplier&MockObject
    {
        return $this->createMock(RulesApplier::class);
    }

    private function rule(int $id, int $appliesToBundleItems): Rule&MockObject
    {
        $rule = $this->createMock(Rule::class);
        $rule->method('getId')->willReturn($id);
        $rule->method('getData')->with('applies_to_bundle_items')->willReturn($appliesToBundleItems);

        return $rule;
    }

    private function bundleRule(bool $exclusive): BundleRule&MockObject
    {
        $rule = $this->createMock(BundleRule::class);
        $rule->method('isExclusive')->willReturn($exclusive);

        return $rule;
    }

    /**
     * @param Rule[] $rules
     * @return int[]
     */
    private function ids(array $rules): array
    {
        return array_values(array_map(static fn (Rule $rule): int => (int) $rule->getId(), $rules));
    }
}
