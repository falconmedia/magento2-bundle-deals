<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Test\Unit\Model\Cli;

use FalconMedia\BundleDeals\Model\Cli\SlotDefinitionParser;
use FalconMedia\BundleDeals\Model\Config\Source\SortBy;
use FalconMedia\BundleDeals\Model\Slot;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SlotDefinitionParserTest extends TestCase
{
    public function testFullDefinition(): void
    {
        self::assertSame([
            'title' => 'Gloves',
            'skus' => ['A-1', 'B-2'],
            'discount_type' => Slot::DISCOUNT_PERCENT,
            'discount_amount' => 10.0,
            'use_as_entry' => 0,
            'sort_by' => SortBy::PRICE_ASC,
            'slot_type' => Slot::TYPE_VARIABLE,
        ], (new SlotDefinitionParser())->parse('Gloves| A-1, B-2 ,A-1 |10%|0|price_asc'));
    }

    public function testDefaults(): void
    {
        $slot = (new SlotDefinitionParser())->parse('Wraps|W-1');

        self::assertSame(Slot::DISCOUNT_NONE, $slot['discount_type']);
        self::assertSame(1, $slot['use_as_entry']);
        self::assertSame(SortBy::POSITION, $slot['sort_by']);
    }

    public function testSlotTypeIsTheSixthField(): void
    {
        $slot = (new SlotDefinitionParser())->parse('Wraps|W-1|10%|0|position|optional');

        self::assertSame(Slot::TYPE_OPTIONAL, $slot['slot_type']);
    }

    public function testUnknownSlotTypeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SlotDefinitionParser())->parse('Wraps|W-1|10%|0|position|bogus');
    }

    public function testFixedDiscountWithComma(): void
    {
        $slot = (new SlotDefinitionParser())->parse('Wraps|W-1|2,50');

        self::assertSame(Slot::DISCOUNT_FIXED, $slot['discount_type']);
        self::assertSame(2.5, $slot['discount_amount']);
    }

    /**
     * @dataProvider invalidProvider
     */
    public function testInvalid(string $definition): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SlotDefinitionParser())->parse($definition);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidProvider(): array
    {
        return [
            'no title' => ['|A-1'],
            'no skus' => ['Gloves|'],
            'bad discount' => ['Gloves|A-1|abc'],
            'percent over 100' => ['Gloves|A-1|150%'],
            'bad entry' => ['Gloves|A-1|10%|yes'],
            'bad sort' => ['Gloves|A-1|10%|1|random'],
        ];
    }
}
