<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Test\Unit\Model\Indexer;

use FalconMedia\BundleDeals\Model\Config\Source\SortBy;
use FalconMedia\BundleDeals\Model\Indexer\CandidateSorter;
use PHPUnit\Framework\TestCase;

class CandidateSorterTest extends TestCase
{
    /**
     * @var array<int, array{name: string, price: float, created_at: string}>
     */
    private array $products = [
        1 => ['name' => 'Charlie', 'price' => 30.0, 'created_at' => '2024-01-01'],
        2 => ['name' => 'alpha', 'price' => 10.0, 'created_at' => '2025-01-01'],
        3 => ['name' => 'Bravo', 'price' => 20.0, 'created_at' => '2023-01-01'],
    ];

    public function testPriceAscending(): void
    {
        self::assertSame([2 => 0, 3 => 1, 1 => 2], (new CandidateSorter())->sort($this->products, SortBy::PRICE_ASC));
    }

    public function testPriceDescending(): void
    {
        self::assertSame([1 => 0, 3 => 1, 2 => 2], (new CandidateSorter())->sort($this->products, SortBy::PRICE_DESC));
    }

    public function testNewestFirst(): void
    {
        self::assertSame([2 => 0, 1 => 1, 3 => 2], (new CandidateSorter())->sort($this->products, SortBy::NEWEST));
    }

    public function testPositionFallsBackToCaseInsensitiveName(): void
    {
        self::assertSame([2 => 0, 3 => 1, 1 => 2], (new CandidateSorter())->sort($this->products, SortBy::POSITION));
    }

    public function testManualLinksComeFirstInTheirOwnOrder(): void
    {
        $positions = (new CandidateSorter())->sort($this->products, SortBy::PRICE_ASC, [1 => 5, 3 => 1]);

        self::assertSame([3 => 0, 1 => 1, 2 => 2], $positions);
    }
}
