<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Test\Unit\Model\Storefront;

use FalconMedia\BundleDeals\Model\Config;
use FalconMedia\BundleDeals\Model\ResourceModel\ProductAttributeValues;
use FalconMedia\BundleDeals\Model\Storefront\ColorMatcher;
use PHPUnit\Framework\TestCase;

class ColorMatcherTest extends TestCase
{
    private const LABELS = [1 => 'Donkerbruin', 2 => 'Roze', 3 => 'Zwart, Wit', 4 => 'Zwart - Zwart', 5 => 'Blauw'];

    /**
     * @param array<int,int[]> $optionIds product id => colour option ids
     * @param int[] $fallbacks colour option ids
     */
    private function matcher(array $optionIds, array $fallbacks = [3, 4], bool $enabled = true): ColorMatcher
    {
        $config = $this->createMock(Config::class);
        $config->method('isColorMatchEnabled')->willReturn($enabled);
        $config->method('getColorMatchAttribute')->willReturn('color');
        $config->method('getColorMatchFallbackOptionIds')->willReturn($fallbacks);
        $values = $this->createMock(ProductAttributeValues::class);
        $values->method('getOptionLabels')->willReturn(self::LABELS);
        $values->method('getOptionIds')->willReturn($optionIds);

        return new ColorMatcher($config, $values);
    }

    public function testSameColourComesFirst(): void
    {
        $matcher = $this->matcher([100 => [1], 11 => [2], 12 => [1], 13 => [3]]);

        self::assertSame([12, 13, 11], $matcher->sortCandidateIds([11, 12, 13], 100, 1));
    }

    public function testFallbackColoursApplyInOrderWhenNothingMatches(): void
    {
        $matcher = $this->matcher([100 => [1], 11 => [2], 12 => [4], 13 => [3]]);

        self::assertSame([13, 12, 11], $matcher->sortCandidateIds([11, 12, 13], 100, 1));
    }

    public function testNoMatchKeepsOriginalOrder(): void
    {
        $matcher = $this->matcher([100 => [1], 11 => [2], 12 => [5]]);

        self::assertSame([11, 12], $matcher->sortCandidateIds([11, 12], 100, 1));
    }

    public function testDisabledKeepsOriginalOrder(): void
    {
        $matcher = $this->matcher([100 => [1], 11 => [2], 12 => [1]], [], false);

        self::assertSame([11, 12], $matcher->sortCandidateIds([11, 12], 100, 1));
    }

    public function testViewedProductWithoutColourUsesFallbacks(): void
    {
        $matcher = $this->matcher([11 => [2], 12 => [3]]);

        self::assertSame([12, 11], $matcher->sortCandidateIds([11, 12], 100, 1));
    }
}
