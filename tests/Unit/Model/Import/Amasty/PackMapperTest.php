<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Test\Unit\Model\Import\Amasty;

use FalconMedia\BundleDeals\Model\Import\Amasty\PackMapper;
use FalconMedia\BundleDeals\Model\Slot;
use PHPUnit\Framework\TestCase;

class PackMapperTest extends TestCase
{
    /**
     * @var PackMapper
     */
    private $mapper;

    /**
     * @var array<int,array<string,mixed>>
     */
    private $products = [
        1 => ['sku' => 'MAIN-1', 'name' => 'Main one'],
        2 => ['sku' => 'MAIN-2', 'name' => 'Main two'],
        10 => ['sku' => 'ITEM-10', 'name' => 'Item ten'],
        11 => ['sku' => 'ITEM-11', 'name' => 'Item eleven'],
    ];

    protected function setUp(): void
    {
        $this->mapper = new PackMapper();
    }

    public function testPercentPackMapsToMainAndItemSlots(): void
    {
        $result = $this->mapper->map($this->pack(), $this->products);

        self::assertSame(PackMapper::STATUS_OK, $result['status']);
        self::assertCount(3, $result['slots']);
        self::assertSame(['MAIN-1'], $result['slots'][0]['skus']);
        self::assertSame('Main one', $result['slots'][0]['title']);
        self::assertSame(1, $result['slots'][0]['use_as_entry']);
        self::assertSame(Slot::DISCOUNT_NONE, $result['slots'][0]['discount_type']);
        self::assertSame(['ITEM-10'], $result['slots'][1]['skus']);
        self::assertSame(0, $result['slots'][1]['use_as_entry']);
        self::assertSame(Slot::DISCOUNT_PERCENT, $result['slots'][1]['discount_type']);
        self::assertSame(15.0, $result['slots'][1]['discount_amount']);
        self::assertSame([10, 20, 30], array_column($result['slots'], 'sort_order'));
        self::assertSame('amasty_pack:5', $result['rule']['external_reference']);
        self::assertSame('Block title', $result['rule']['title']);
        self::assertSame('2024-01-01', $result['rule']['from_date']);
        self::assertSame([1], $result['entry_product_ids']);
    }

    public function testFixedDiscountType(): void
    {
        $result = $this->mapper->map($this->pack(['discount_type' => 0, 'discount_amount' => '7.5']), $this->products);

        self::assertSame(Slot::DISCOUNT_FIXED, $result['slots'][1]['discount_type']);
        self::assertSame(7.5, $result['slots'][1]['discount_amount']);
    }

    public function testConditionalDiscountFallsBackToPercentWithWarning(): void
    {
        $result = $this->mapper->map($this->pack(['discount_type' => 2]), $this->products);

        self::assertSame(Slot::DISCOUNT_PERCENT, $result['slots'][1]['discount_type']);
        self::assertContains(PackMapper::WARN_CONDITIONAL_DISCOUNT, array_column($result['warnings'], 'type'));
    }

    public function testApplyForParentDiscountsTheMainSlot(): void
    {
        $result = $this->mapper->map($this->pack(['apply_for_parent' => 1]), $this->products);

        self::assertSame(Slot::DISCOUNT_PERCENT, $result['slots'][0]['discount_type']);
        self::assertSame(15.0, $result['slots'][0]['discount_amount']);
    }

    public function testPerItemDiscountOverridesPackAmount(): void
    {
        $info = json_encode([
            '10' => ['entity_id' => '10', 'quantity' => '1', 'discount_amount' => '25'],
            '11' => ['entity_id' => '11', 'quantity' => '1', 'discount_amount' => ''],
        ]);
        $result = $this->mapper->map($this->pack(['products_info' => $info]), $this->products);

        self::assertSame(25.0, $result['slots'][1]['discount_amount']);
        self::assertSame(15.0, $result['slots'][2]['discount_amount']);
        self::assertSame(
            [PackMapper::WARN_PER_ITEM_DISCOUNT],
            array_values(array_diff(array_column($result['warnings'], 'type'), [PackMapper::WARN_CART_MESSAGE]))
        );
    }

    public function testAllGroupsValueMeansEveryGroup(): void
    {
        self::assertSame([], $this->mapper->mapCustomerGroups('32000'));
        self::assertSame([], $this->mapper->mapCustomerGroups('0,32000'));
        self::assertSame([0, 1], $this->mapper->mapCustomerGroups('0,1'));
        $result = $this->mapper->map($this->pack(['customer_group_ids' => '32000']), $this->products);
        self::assertSame([], $result['rule']['customer_group_ids']);
    }

    public function testMultipleMainProductsShareOneSlotTitledAfterThePack(): void
    {
        $result = $this->mapper->map($this->pack(['parent_ids' => [1, 2]]), $this->products);

        self::assertSame(['MAIN-1', 'MAIN-2'], $result['slots'][0]['skus']);
        self::assertSame('Block title', $result['slots'][0]['title']);
    }

    public function testMissingItemProductSkipsThePack(): void
    {
        $result = $this->mapper->map($this->pack(['product_ids' => '10,99']), $this->products);

        self::assertSame(PackMapper::STATUS_SKIP, $result['status']);
        self::assertContains(PackMapper::WARN_MISSING_PRODUCT, array_column($result['warnings'], 'type'));
    }

    public function testAnyProductsConditionAndStatusHandling(): void
    {
        $pack = $this->pack(['apply_condition' => 0, 'status' => 0]);

        $default = $this->mapper->map($pack, $this->products);
        self::assertSame(1, $default['rule']['is_active']);
        self::assertContains(PackMapper::WARN_APPLY_CONDITION_ANY, array_column($default['warnings'], 'type'));
        self::assertSame(0, $this->mapper->map($pack, $this->products, true)['rule']['is_active']);
        self::assertSame(1, $this->mapper->map($pack, $this->products, false, true)['slots'][1]['use_as_entry']);
    }

    /**
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private function pack(array $override = []): array
    {
        return array_merge([
            'pack_id' => 5,
            'status' => 1,
            'priority' => 3,
            'name' => 'Pack name',
            'block_title' => 'Block title',
            'customer_group_ids' => '0,1',
            'product_ids' => '10,11',
            'discount_type' => 1,
            'apply_for_parent' => 0,
            'discount_amount' => '15',
            'cart_message' => 'Save {discount_amount}',
            'date_from' => '2024-01-01 00:00:00',
            'date_to' => null,
            'products_info' => '',
            'apply_condition' => 1,
            'parent_ids' => [1],
            'store_ids' => [3],
        ], $override);
    }

    public function testVariantChildrenAreReplacedByTheirParentAndMerged(): void
    {
        $products = $this->products + [
            20 => ['sku' => 'SHORTS', 'name' => 'Shorts', 'parent_id' => null],
            21 => ['sku' => 'SHORTS-M', 'name' => 'Shorts M', 'parent_id' => 20],
            22 => ['sku' => 'SHORTS-L', 'name' => 'Shorts L', 'parent_id' => 20],
            23 => ['sku' => 'MAIN-1-S', 'name' => 'Main one S', 'parent_id' => 1],
        ];
        $pack = $this->pack(['parent_ids' => [1, 23], 'product_ids' => '21,22,20,23']);

        $mapped = $this->mapper->map($pack, $products);

        $this->assertSame(PackMapper::STATUS_OK, $mapped['status']);
        $this->assertCount(2, $mapped['slots']);
        $this->assertSame(['MAIN-1'], $mapped['slots'][0]['skus']);
        $this->assertSame(['SHORTS'], $mapped['slots'][1]['skus']);
        $types = array_column($mapped['warnings'], 'type');
        $this->assertContains(PackMapper::WARN_CHILD_TO_PARENT, $types);
    }
}
