<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Test\Unit\Model\Set;

use FalconMedia\BundleDeals\Model\Set\SetAllocator;
use PHPUnit\Framework\TestCase;

class SetAllocatorTest extends TestCase
{
    private const CHALLENGER_GLOVES = 1;
    private const ELITE_GLOVES = 2;
    private const SHIN_GUARDS = 3;
    private const HAND_WRAPS = 4;

    private SetAllocator $allocator;

    protected function setUp(): void
    {
        $this->allocator = new SetAllocator();
    }

    /**
     * Venum Elite set (rule 1) and Venum starter set (rule 3) share the Challenger gloves and the shin guards.
     *
     * @return array<int, array<int, int[]>>
     */
    private function overlappingRules(): array
    {
        return [
            3 => [7 => [self::CHALLENGER_GLOVES], 8 => [self::SHIN_GUARDS]],
            1 => [
                1 => [self::ELITE_GLOVES, self::CHALLENGER_GLOVES],
                2 => [self::SHIN_GUARDS],
                3 => [self::HAND_WRAPS],
            ],
        ];
    }

    /**
     * @return array<int, array{type:string, amount:float}>
     */
    private function discounts(): array
    {
        return [
            1 => ['type' => 'percent', 'amount' => 10.0],
            2 => ['type' => 'percent', 'amount' => 10.0],
            3 => ['type' => 'percent', 'amount' => 5.0],
            7 => ['type' => 'fixed', 'amount' => 5.0],
            8 => ['type' => 'fixed', 'amount' => 5.0],
        ];
    }

    /**
     * @return array<string, array{product_id:int, qty:float, price:float}>
     */
    private function cart(float $shinGuardQty): array
    {
        return [
            'challenger' => ['product_id' => self::CHALLENGER_GLOVES, 'qty' => 1.0, 'price' => 54.99],
            'elite' => ['product_id' => self::ELITE_GLOVES, 'qty' => 1.0, 'price' => 89.99],
            'shin' => ['product_id' => self::SHIN_GUARDS, 'qty' => $shinGuardQty, 'price' => 109.99],
            'wraps' => ['product_id' => self::HAND_WRAPS, 'qty' => 1.0, 'price' => 7.99],
        ];
    }

    /**
     * @param array<int, array<string, array<int, int>>> $allocation
     * @param array<string, array{product_id:int, qty:float, price:float}> $lines
     */
    private function totalDiscount(array $allocation, array $lines): float
    {
        $total = 0.0;
        foreach ($allocation as $linesBySlot) {
            foreach ($linesBySlot as $lineKey => $slots) {
                foreach ($slots as $slotId => $units) {
                    $discount = $this->discounts()[$slotId];
                    $price = $lines[$lineKey]['price'];
                    $perUnit = $discount['type'] === 'percent'
                        ? round($price * $discount['amount'] / 100, 2)
                        : min($discount['amount'], $price);
                    $total += $perUnit * $units;
                }
            }
        }

        return round($total, 2);
    }

    public function testOverlappingDealsShareNoUnitAndGiveTheHighestDiscount(): void
    {
        $lines = $this->cart(2.0);
        $allocation = $this->allocator->allocate($this->overlappingRules(), $lines, $this->discounts());

        self::assertSame([
            'elite' => [1 => 1],
            'shin' => [2 => 1],
            'wraps' => [3 => 1],
        ], $allocation[1]);
        self::assertSame([
            'challenger' => [7 => 1],
            'shin' => [8 => 1],
        ], $allocation[3]);
        self::assertSame(30.40, $this->totalDiscount($allocation, $lines));
    }

    public function testOneSharedUnitGoesToTheDealWithTheHighestDiscount(): void
    {
        $lines = $this->cart(1.0);
        $allocation = $this->allocator->allocate($this->overlappingRules(), $lines, $this->discounts());

        self::assertSame([1], array_keys($allocation));
        self::assertSame(['elite' => [1 => 1], 'shin' => [2 => 1], 'wraps' => [3 => 1]], $allocation[1]);
        self::assertSame(20.40, $this->totalDiscount($allocation, $lines));
    }

    public function testOptionalSlotMayStayEmpty(): void
    {
        $rules = [1 => [1 => [10], 2 => [20], 3 => [30]]];
        $lines = [
            'a' => ['product_id' => 10, 'qty' => 1.0, 'price' => 50.0],
            'b' => ['product_id' => 20, 'qty' => 1.0, 'price' => 40.0],
        ];
        $discounts = [
            1 => ['type' => 'percent', 'amount' => 10.0],
            2 => ['type' => 'percent', 'amount' => 10.0],
            3 => ['type' => 'percent', 'amount' => 10.0],
        ];

        self::assertSame([], $this->allocator->allocate($rules, $lines, $discounts));
        self::assertSame(
            [1 => ['a' => [1 => 1], 'b' => [2 => 1]]],
            $this->allocator->allocate($rules, $lines, $discounts, [3])
        );
    }

    public function testNonOverlappingDealsAreBothComplete(): void
    {
        $rules = [
            1 => [1 => [10], 2 => [20]],
            2 => [3 => [30], 4 => [40]],
        ];
        $lines = [
            'a' => ['product_id' => 10, 'qty' => 1.0, 'price' => 50.0],
            'b' => ['product_id' => 20, 'qty' => 1.0, 'price' => 50.0],
            'c' => ['product_id' => 30, 'qty' => 2.0, 'price' => 20.0],
            'd' => ['product_id' => 40, 'qty' => 2.0, 'price' => 20.0],
        ];
        $discounts = [
            1 => ['type' => 'percent', 'amount' => 10.0],
            2 => ['type' => 'percent', 'amount' => 10.0],
            3 => ['type' => 'fixed', 'amount' => 2.0],
            4 => ['type' => 'fixed', 'amount' => 2.0],
        ];

        self::assertSame([
            1 => ['a' => [1 => 1], 'b' => [2 => 1]],
            2 => ['c' => [3 => 2], 'd' => [4 => 2]],
        ], $this->allocator->allocate($rules, $lines, $discounts));
    }

    public function testEqualDiscountGoesToTheLowestDealId(): void
    {
        $rules = [
            5 => [1 => [10], 2 => [20]],
            4 => [3 => [10], 4 => [20]],
        ];
        $lines = [
            'a' => ['product_id' => 10, 'qty' => 1.0, 'price' => 10.0],
            'b' => ['product_id' => 20, 'qty' => 1.0, 'price' => 10.0],
        ];
        $discounts = array_fill_keys([1, 2, 3, 4], ['type' => 'fixed', 'amount' => 1.0]);

        self::assertSame(
            [4 => ['a' => [3 => 1], 'b' => [4 => 1]]],
            $this->allocator->allocate($rules, $lines, $discounts)
        );
    }

    public function testWithoutPricesTheMostUnitsAreCovered(): void
    {
        $rules = [1 => [1 => [10], 2 => [20]]];
        $lines = [
            'a' => ['product_id' => 10, 'qty' => 3.0],
            'b' => ['product_id' => 20, 'qty' => 2.0],
        ];

        self::assertSame(
            [1 => ['a' => [1 => 2], 'b' => [2 => 2]]],
            $this->allocator->allocate($rules, $lines, [])
        );
    }

    public function testIncompleteSetCoversNothing(): void
    {
        $rules = [1 => [1 => [10], 2 => [20]]];
        $lines = ['a' => ['product_id' => 10, 'qty' => 1.0, 'price' => 10.0]];

        self::assertSame([], $this->allocator->allocate($rules, $lines, []));
    }
}
