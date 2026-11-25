<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Cli;

use FalconMedia\BundleDeals\Model\Config\Source\SortBy;
use FalconMedia\BundleDeals\Model\Slot;
use InvalidArgumentException;

/**
 * Parses a slot definition of the create command: "Title|SKU1,SKU2|discount|entry|sort|type".
 *
 * Discount is "none", "10%" (percent) or "5" (fixed amount per item). Entry is 1 or 0, sort a SortBy value, type fixed, variable (default) or optional.
 */
class SlotDefinitionParser
{
    private const SORT_VALUES = [
        SortBy::POSITION,
        SortBy::PRICE_ASC,
        SortBy::PRICE_DESC,
        SortBy::NAME,
        SortBy::NEWEST,
    ];

    /**
     * Parses a slot definition given on the command line.
     *
     * @return array{
     *     title: string,
     *     skus: string[],
     *     discount_type: string,
     *     discount_amount: float,
     *     use_as_entry: int,
     *     sort_by: string,
     *     slot_type: string
     * }
     * @throws InvalidArgumentException
     */
    public function parse(string $definition): array
    {
        $parts = array_map('trim', explode('|', $definition));
        $title = $parts[0] ?? '';
        if ($title === '') {
            throw new InvalidArgumentException(sprintf('Slot "%s" has no title.', $definition));
        }

        $skus = array_values(array_unique(array_filter(
            array_map('trim', explode(',', $parts[1] ?? '')),
            static fn (string $sku): bool => $sku !== ''
        )));
        if (!$skus) {
            throw new InvalidArgumentException(sprintf('Slot "%s" has no SKUs.', $title));
        }

        [$discountType, $discountAmount] = $this->parseDiscount($parts[2] ?? '', $title);

        $entry = $parts[3] ?? '';
        if (!in_array($entry, ['', '0', '1'], true)) {
            throw new InvalidArgumentException(sprintf('Slot "%s": entry must be 1 or 0.', $title));
        }

        $sortBy = ($parts[4] ?? '') === '' ? SortBy::POSITION : $parts[4];
        if (!in_array($sortBy, self::SORT_VALUES, true)) {
            throw new InvalidArgumentException(sprintf(
                'Slot "%s": sort must be one of %s.',
                $title,
                implode(', ', self::SORT_VALUES)
            ));
        }

        $slotType = ($parts[5] ?? '') === '' ? Slot::TYPE_VARIABLE : strtolower($parts[5]);
        if (!in_array($slotType, [Slot::TYPE_FIXED, Slot::TYPE_VARIABLE, Slot::TYPE_OPTIONAL], true)) {
            throw new InvalidArgumentException(sprintf(
                'Slot "%s": type must be fixed, variable or optional.',
                $title
            ));
        }

        return [
            'title' => $title,
            'skus' => $skus,
            'discount_type' => $discountType,
            'discount_amount' => $discountAmount,
            'use_as_entry' => $entry === '0' ? 0 : 1,
            'sort_by' => $sortBy,
            'slot_type' => $slotType,
        ];
    }

    /**
     * Parses a discount value into its type and amount.
     *
     * @return array{0: string, 1: float}
     * @throws InvalidArgumentException
     */
    private function parseDiscount(string $value, string $title): array
    {
        $value = strtolower(str_replace(' ', '', $value));
        if ($value === '' || $value === 'none' || $value === '0') {
            return [Slot::DISCOUNT_NONE, 0.0];
        }

        $isPercent = str_ends_with($value, '%');
        $number = str_replace(',', '.', rtrim($value, '%'));
        if (!is_numeric($number) || (float) $number < 0) {
            throw new InvalidArgumentException(sprintf('Slot "%s": invalid discount "%s".', $title, $value));
        }
        if ($isPercent && (float) $number > 100) {
            throw new InvalidArgumentException(sprintf('Slot "%s": a percentage cannot exceed 100.', $title));
        }

        return [$isPercent ? Slot::DISCOUNT_PERCENT : Slot::DISCOUNT_FIXED, (float) $number];
    }
}
