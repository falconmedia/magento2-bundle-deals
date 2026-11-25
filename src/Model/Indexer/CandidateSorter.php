<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Indexer;

use FalconMedia\BundleDeals\Model\Config\Source\SortBy;

/**
 * Turns matched products into positions; manual links come first in their own order.
 */
class CandidateSorter
{
    /**
     * Sorts the candidate products and returns their positions.
     *
     * @param array<int,array{name:string,price:float,created_at:string}> $products
     * @param array<int,int> $manualPositions product id => position
     * @return array<int, int> product id => position, 0 is the default product
     */
    public function sort(array $products, string $sortBy, array $manualPositions = []): array
    {
        $ids = array_keys($products);
        usort($ids, function (int $a, int $b) use ($products, $sortBy, $manualPositions): int {
            $manualA = array_key_exists($a, $manualPositions);
            $manualB = array_key_exists($b, $manualPositions);
            if ($manualA !== $manualB) {
                return $manualA ? -1 : 1;
            }
            if ($manualA && $manualPositions[$a] !== $manualPositions[$b]) {
                return $manualPositions[$a] <=> $manualPositions[$b];
            }

            $result = match ($sortBy) {
                SortBy::PRICE_ASC => $products[$a]['price'] <=> $products[$b]['price'],
                SortBy::PRICE_DESC => $products[$b]['price'] <=> $products[$a]['price'],
                SortBy::NEWEST => strcmp($products[$b]['created_at'], $products[$a]['created_at']),
                default => 0,
            };

            return $result
                ?: strcasecmp($products[$a]['name'], $products[$b]['name'])
                ?: $a <=> $b;
        });

        return array_flip($ids);
    }
}
