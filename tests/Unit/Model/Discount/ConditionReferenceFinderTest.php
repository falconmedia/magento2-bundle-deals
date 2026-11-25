<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Test\Unit\Model\Discount;

use FalconMedia\BundleDeals\Model\Discount\ConditionReferenceFinder;
use FalconMedia\BundleDeals\Model\Rule\Condition\ItemInSet;
use FalconMedia\BundleDeals\Model\Rule\Condition\SetComplete;
use PHPUnit\Framework\TestCase;

class ConditionReferenceFinderTest extends TestCase
{
    public function testFindsRequiredReferenceInAllTrueTree(): void
    {
        $tree = [
            'type' => 'combine',
            'aggregator' => 'all',
            'value' => '1',
            'conditions' => [
                ['type' => SetComplete::class, 'operator' => '==', 'value' => '7'],
                ['type' => 'other', 'operator' => '==', 'value' => '9'],
            ],
        ];

        self::assertSame([7], (new ConditionReferenceFinder())->findRequiredRuleIds($tree));
    }

    public function testIgnoresAnyAggregatorAndNegation(): void
    {
        $finder = new ConditionReferenceFinder();
        $any = [
            'aggregator' => 'any',
            'value' => '1',
            'conditions' => [['type' => SetComplete::class, 'operator' => '==', 'value' => '7']],
        ];
        $negated = [
            'aggregator' => 'all',
            'value' => '1',
            'conditions' => [['type' => SetComplete::class, 'operator' => '!=', 'value' => '7']],
        ];

        self::assertSame([], $finder->findRequiredRuleIds($any));
        self::assertSame([], $finder->findRequiredRuleIds($negated));
    }

    public function testSkipsSlotScopedValues(): void
    {
        $tree = [
            'aggregator' => 'all',
            'value' => '1',
            'conditions' => [
                ['type' => ItemInSet::class, 'operator' => '==', 'value' => '3:5'],
                ['type' => ItemInSet::class, 'operator' => '==', 'value' => '4'],
            ],
        ];

        self::assertSame([4], (new ConditionReferenceFinder())->findRequiredRuleIds($tree));
    }

    public function testEmptyTree(): void
    {
        self::assertSame([], (new ConditionReferenceFinder())->findRequiredRuleIds(null));
    }
}
