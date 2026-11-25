<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Import\Amasty;

/**
 * Groups imported packs into colour groups: by style group value, by colour variant links, then by name suffix.
 */
class ColorGroupResolver
{
    public const SOURCE_STYLE_GROUP = 'style_group';
    public const SOURCE_LINK = 'color_variant_link';
    public const SOURCE_NAME = 'name_suffix';

    private const SUFFIX_SEPARATOR = ' - ';
    private const MAX_CODE_LENGTH = 64;

    /**
     * Resolves colour groups for a set of packs.
     *
     * Each member holds "name" (string), "entry_product_ids" (int[]) and optionally "style_group" (string).
     * Links map a product id to the product ids it is a colour variant of. Known colours are option labels;
     * an empty list accepts any name suffix as a colour.
     *
     * @param array<int,array<string,mixed>> $members keyed by pack id
     * @param array<int,int[]> $links
     * @param string[] $knownColors
     * @return array<string,array{code:string, source:string, base_name:string, members:array<int,int>}>
     *     keyed by code; members map pack id to colour sort order
     */
    public function resolve(array $members, array $links = [], array $knownColors = []): array
    {
        ksort($members);
        $remaining = $members;
        $groups = [];

        $byStyle = [];
        foreach ($remaining as $packId => $member) {
            $style = trim((string) ($member['style_group'] ?? ''));
            if ($style !== '') {
                $byStyle[mb_strtolower($style)][] = $packId;
            }
        }
        foreach ($byStyle as $style => $packIds) {
            if (count($packIds) > 1) {
                $groups[] = ['base' => (string) $style, 'source' => self::SOURCE_STYLE_GROUP, 'packs' => $packIds];
                $remaining = array_diff_key($remaining, array_flip($packIds));
            }
        }

        foreach ($this->groupByLinks($remaining, $links) as $packIds) {
            $first = $remaining[$packIds[0]];
            $groups[] = [
                'base' => $this->splitName((string) $first['name'])[0],
                'source' => self::SOURCE_LINK,
                'packs' => $packIds,
            ];
            $remaining = array_diff_key($remaining, array_flip($packIds));
        }

        $known = array_flip(array_map('mb_strtolower', array_map('trim', $knownColors)));
        $byBase = [];
        $suffixes = [];
        foreach ($remaining as $packId => $member) {
            [$base, $suffix] = $this->splitName((string) $member['name']);
            if ($suffix !== '' && $this->isColor($suffix, $known)) {
                $byBase[mb_strtolower($base)][] = $packId;
                $suffixes[mb_strtolower($base)][] = mb_strtolower($suffix);
            }
        }
        foreach ($byBase as $base => $packIds) {
            // Packs sharing base name and colour are different deals, not colour versions of one bundle.
            if (count($packIds) > 1 && count(array_unique($suffixes[$base])) === count($packIds)) {
                $groups[] = [
                    'base' => $this->splitName((string) $members[$packIds[0]]['name'])[0],
                    'source' => self::SOURCE_NAME,
                    'packs' => $packIds,
                ];
            }
        }

        $result = [];
        foreach ($groups as $group) {
            $code = $this->uniqueCode($this->slug($group['base']), $result);
            $packIds = $group['packs'];
            sort($packIds);
            $order = [];
            foreach ($packIds as $index => $packId) {
                $order[$packId] = ($index + 1) * 10;
            }
            $result[$code] = [
                'code' => $code,
                'source' => $group['source'],
                'base_name' => $group['base'],
                'members' => $order,
            ];
        }

        return $result;
    }

    /**
     * Splits a name into base name and the colour suffix after the last " - ".
     *
     * @return array{0:string, 1:string}
     */
    public function splitName(string $name): array
    {
        $name = trim($name);
        $position = strrpos($name, self::SUFFIX_SEPARATOR);
        if ($position === false) {
            return [$name, ''];
        }

        return [
            trim(substr($name, 0, $position)),
            trim(substr($name, $position + strlen(self::SUFFIX_SEPARATOR))),
        ];
    }

    /**
     * Turns a name into a lowercase code of letters, digits and hyphens.
     */
    public function slug(string $value): string
    {
        $value = mb_strtolower(trim($value));
        if (function_exists('transliterator_transliterate')) {
            $converted = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);
            $value = is_string($converted) ? $converted : $value;
        }
        $value = str_replace(["'", '"', '`', '^', '~'], '', $value);
        $value = trim((string) preg_replace('/[^a-z0-9]+/', '-', $value), '-');

        return substr($value !== '' ? $value : 'group', 0, self::MAX_CODE_LENGTH);
    }

    /**
     * Connected components of packs whose entry products are colour variant links of each other.
     *
     * @param array<int,array<string,mixed>> $members
     * @param array<int,int[]> $links
     * @return int[][] pack ids per component with at least two packs
     */
    private function groupByLinks(array $members, array $links): array
    {
        $packByProduct = [];
        foreach ($members as $packId => $member) {
            foreach ((array) ($member['entry_product_ids'] ?? []) as $productId) {
                $packByProduct[(int) $productId][] = $packId;
            }
        }

        $parent = [];
        foreach (array_keys($members) as $packId) {
            $parent[$packId] = $packId;
        }
        $find = static function (int $id) use (&$parent): int {
            while ($parent[$id] !== $id) {
                $parent[$id] = $parent[$parent[$id]];
                $id = $parent[$id];
            }

            return $id;
        };

        foreach ($members as $packId => $member) {
            foreach ((array) ($member['entry_product_ids'] ?? []) as $productId) {
                foreach ($links[(int) $productId] ?? [] as $linkedId) {
                    foreach ($packByProduct[(int) $linkedId] ?? [] as $otherPack) {
                        if ($otherPack === $packId) {
                            continue;
                        }
                        $rootA = $find($packId);
                        $rootB = $find($otherPack);
                        if ($rootA !== $rootB) {
                            $parent[max($rootA, $rootB)] = min($rootA, $rootB);
                        }
                    }
                }
            }
        }

        $components = [];
        foreach (array_keys($members) as $packId) {
            $components[$find($packId)][] = $packId;
        }

        return array_values(array_filter($components, static function (array $packIds): bool {
            return count($packIds) > 1;
        }));
    }

    /**
     * Tells whether a suffix such as "Zwart/Wit" consists of known colours.
     *
     * @param array<string,int> $known
     */
    private function isColor(string $suffix, array $known): bool
    {
        if (!$known) {
            return true;
        }
        foreach (preg_split('/\s*\/\s*/', mb_strtolower($suffix)) ?: [] as $part) {
            if ($part === '' || !isset($known[$part])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Makes a code unique among resolved groups with a numeric suffix.
     *
     * @param array<string,mixed> $existing
     */
    private function uniqueCode(string $code, array $existing): string
    {
        $candidate = $code;
        $counter = 2;
        while (isset($existing[$candidate])) {
            $suffix = '-' . ($counter++);
            $candidate = substr($code, 0, self::MAX_CODE_LENGTH - strlen($suffix)) . $suffix;
        }

        return $candidate;
    }
}
