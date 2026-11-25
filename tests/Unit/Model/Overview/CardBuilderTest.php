<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Test\Unit\Model\Overview;

use FalconMedia\BundleDeals\Model\Overview\CardBuilder;
use PHPUnit\Framework\TestCase;

class CardBuilderTest extends TestCase
{
    /**
     * @var CardBuilder
     */
    private $builder;

    protected function setUp(): void
    {
        $this->builder = new CardBuilder();
    }

    public function testTotalsApplyPercentAndFixedDiscounts(): void
    {
        $totals = $this->builder->getTotals($this->bundle(1, null, null, [
            [100.0, 120.0, 'none', 0],
            [50.0, 50.0, 'percent', 20],
            [30.0, 30.0, 'fixed', 40],
        ]));

        $this->assertSame(180.0, $totals['sumPrice']);
        $this->assertSame(140.0, $totals['setPrice']);
        $this->assertSame(40.0, $totals['saving']);
        $this->assertSame(100, $totals['maxDiscountPercent']);
    }

    public function testOneCardPerColourGroupWithLowestAvailableSortOrder(): void
    {
        $bundles = [
            $this->bundle(10, 'set-a', 20),
            $this->bundle(11, 'set-a', 10),
            $this->bundle(12, 'set-a', 30),
            $this->bundle(13, null, null),
        ];
        $bundles[1]['available'] = false;

        $cards = $this->builder->buildCards($bundles, [], ['set-a' => [['ruleId' => 10], ['ruleId' => 12]]]);

        $this->assertCount(2, $cards);
        $this->assertSame(10, $cards[0]['ruleId']);
        $this->assertCount(2, $cards[0]['variants']);
        $this->assertSame(13, $cards[1]['ruleId']);
        $this->assertSame([], $cards[1]['variants']);
    }

    public function testCardTitleUsesRuleNameOverBlockTitle(): void
    {
        $named = $this->bundle(10, null, null);
        $named['name'] = 'Venum Elite Set - Black';
        $unnamed = $this->bundle(11, null, null);
        $unnamed['name'] = '';

        $cards = $this->builder->buildCards([$named, $unnamed], []);

        $this->assertSame('Venum Elite Set - Black', $cards[0]['title']);
        $this->assertSame('Bundle 11', $cards[1]['title']);
    }

    public function testGroupWithoutAvailableMemberIsLeftOut(): void
    {
        $bundle = $this->bundle(10, 'set-a', 10);
        $bundle['available'] = false;

        $this->assertSame([], $this->builder->buildCards([$bundle], []));
    }

    public function testFacetsAreCrossFilteredAndCountCards(): void
    {
        $cards = $this->builder->buildCards(
            [$this->bundle(1, 'g', 10), $this->bundle(2, 'g', 20), $this->bundle(3, null, null)],
            [
                1 => ['brand' => 'Venum', 'brandSlug' => 'venum', 'sports' => ['boksen' => 'Boksen']],
                2 => ['brand' => 'Venum', 'brandSlug' => 'venum', 'sports' => ['boksen' => 'Boksen']],
                3 => ['brand' => 'Booster', 'brandSlug' => 'booster', 'sports' => ['mma' => 'MMA']],
            ]
        );
        $facets = $this->builder->getFacets($cards, ['brand' => 'venum']);

        $this->assertSame(1, $facets['sport']['boksen']['count']);
        $this->assertArrayNotHasKey('mma', $facets['sport']);
        $this->assertSame(1, $facets['brand']['venum']['count']);
        $this->assertSame(1, $facets['brand']['booster']['count']);
        $this->assertCount(1, $this->builder->filterCards($cards, ['sport' => 'mma']));
    }

    public function testSortByPriceAndNewestKeepsPositionOnTies(): void
    {
        $cards = $this->builder->buildCards([
            $this->bundle(1, null, null, [[50.0, 50.0, 'none', 0], [50.0, 50.0, 'none', 0]]),
            $this->bundle(2, null, null, [[10.0, 10.0, 'none', 0], [10.0, 10.0, 'none', 0]]),
            $this->bundle(3, null, null, [[10.0, 10.0, 'none', 0], [10.0, 10.0, 'none', 0]]),
        ], []);

        $this->assertSame([2, 3, 1], array_column($this->builder->sortCards($cards, 'price_asc'), 'ruleId'));
        $this->assertSame([3, 2, 1], array_column($this->builder->sortCards($cards, 'newest'), 'ruleId'));
        $this->assertSame([1, 2, 3], array_column($this->builder->sortCards($cards, 'popular'), 'ruleId'));
    }

    /**
     * @param array<int,array{0:float, 1:float, 2:string, 3:float}> $slots final, regular, type, amount
     * @return array<string,mixed>
     */
    private function bundle(int $ruleId, ?string $group, ?int $sortOrder, array $slots = []): array
    {
        $slots = $slots ?: [[10.0, 10.0, 'none', 0], [20.0, 20.0, 'percent', 10]];
        $data = [];
        foreach ($slots as $index => $slot) {
            $data[] = [
                'title' => 'Slot ' . $index,
                'isEntry' => $index === 0,
                'discount' => ['type' => $slot[2], 'amount' => $slot[3]],
                'candidates' => [[
                    'productId' => $ruleId * 10 + $index,
                    'name' => 'Product ' . $index,
                    'image' => 'img' . $index . '.jpg',
                    'finalPrice' => $slot[0],
                    'regularPrice' => $slot[1],
                ]],
            ];
        }

        return [
            'ruleId' => $ruleId,
            'title' => 'Bundle ' . $ruleId,
            'priority' => 0,
            'colorGroup' => $group,
            'colorOptionId' => null,
            'colorSortOrder' => $sortOrder,
            'available' => true,
            'url' => 'https://example.test/p' . $ruleId . '.html',
            'entryProductId' => $ruleId * 10,
            'slots' => $data,
        ];
    }
}
