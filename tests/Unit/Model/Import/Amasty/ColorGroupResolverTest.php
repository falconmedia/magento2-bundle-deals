<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Test\Unit\Model\Import\Amasty;

use FalconMedia\BundleDeals\Model\Import\Amasty\ColorGroupResolver;
use PHPUnit\Framework\TestCase;

class ColorGroupResolverTest extends TestCase
{
    /**
     * @var ColorGroupResolver
     */
    private $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ColorGroupResolver();
    }

    public function testNameSuffixGroupsColourVersions(): void
    {
        $members = [
            14 => ['name' => 'Venum Kickboks Set Elite - Zwart/Wit', 'entry_product_ids' => [4]],
            8 => ['name' => 'Venum Kickboks Set Elite - Wit', 'entry_product_ids' => [1]],
            13 => ['name' => 'Venum Kickboks Set Elite - Zwart/Goud', 'entry_product_ids' => [3]],
            20 => ['name' => 'Other Set - Wit', 'entry_product_ids' => [9]],
        ];
        $groups = $this->resolver->resolve($members, [], ['Wit', 'Zwart', 'Goud']);

        self::assertSame(['venum-kickboks-set-elite'], array_keys($groups));
        self::assertSame([8 => 10, 13 => 20, 14 => 30], $groups['venum-kickboks-set-elite']['members']);
        self::assertSame(ColorGroupResolver::SOURCE_NAME, $groups['venum-kickboks-set-elite']['source']);
    }

    public function testUnknownColourSuffixOrSameColourIsNotGrouped(): void
    {
        $members = [
            1 => ['name' => 'Set - Large', 'entry_product_ids' => [1]],
            2 => ['name' => 'Set - Small', 'entry_product_ids' => [2]],
            3 => ['name' => 'Combideal - Zwart', 'entry_product_ids' => [3]],
            4 => ['name' => 'Combideal - Zwart', 'entry_product_ids' => [4]],
        ];

        self::assertSame([], $this->resolver->resolve($members, [], ['Zwart', 'Wit']));
    }

    public function testColourVariantLinksWinOverNameSuffix(): void
    {
        $members = [
            5 => ['name' => 'Gloves Red', 'entry_product_ids' => [50]],
            6 => ['name' => 'Gloves Blue', 'entry_product_ids' => [60]],
            7 => ['name' => 'Unrelated', 'entry_product_ids' => [70]],
        ];
        $groups = $this->resolver->resolve($members, [50 => [60], 60 => [50]]);

        self::assertSame(['gloves-red'], array_keys($groups));
        self::assertSame([5 => 10, 6 => 20], $groups['gloves-red']['members']);
        self::assertSame(ColorGroupResolver::SOURCE_LINK, $groups['gloves-red']['source']);
    }

    public function testStyleGroupAttributeComesFirst(): void
    {
        $members = [
            1 => ['name' => 'A - Wit', 'entry_product_ids' => [1], 'style_group' => 'Style X'],
            2 => ['name' => 'B - Zwart', 'entry_product_ids' => [2], 'style_group' => 'style x'],
        ];
        $groups = $this->resolver->resolve($members, [], ['Wit', 'Zwart']);

        self::assertSame(['style-x'], array_keys($groups));
        self::assertSame(ColorGroupResolver::SOURCE_STYLE_GROUP, $groups['style-x']['source']);
    }

    public function testSingleMemberGetsNoGroup(): void
    {
        $members = [1 => ['name' => 'Lonely Set - Wit', 'entry_product_ids' => [1]]];

        self::assertSame([], $this->resolver->resolve($members, [], ['Wit']));
    }

    public function testSlug(): void
    {
        self::assertSame('booster-elite-jeugd-combideal', $this->resolver->slug(' Booster Elite Jeugd Combideal '));
        self::assertSame('cafe-set', $this->resolver->slug('Café & Set'));
    }
}
