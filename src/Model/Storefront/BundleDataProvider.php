<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Model\Storefront;

use FalconMedia\BundleDeals\Api\DeliveryTimeProviderInterface;
use FalconMedia\BundleDeals\Model\Config;
use FalconMedia\BundleDeals\Model\Discount\DiscountEstimator;
use FalconMedia\BundleDeals\Model\ResourceModel\Index;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot\CollectionFactory as SlotCollectionFactory;
use FalconMedia\BundleDeals\Model\Rule;
use FalconMedia\BundleDeals\Model\Set\ActiveRuleProvider;
use FalconMedia\BundleDeals\Model\Slot;
use Magento\Catalog\Model\Product;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds the storefront data of a bundle deal as seen from one member product.
 */
class BundleDataProvider
{
    /**
     * @var string[]
     */
    private array $identities = [];

    /**
     * @var array<string,array<string,mixed>|null>
     */
    private array $productCache = [];

    public function __construct(
        private readonly Config $config,
        private readonly Index $index,
        private readonly ActiveRuleProvider $activeRuleProvider,
        private readonly SlotCollectionFactory $slotCollectionFactory,
        private readonly ProductDataBuilder $productDataBuilder,
        private readonly DiscountEstimator $discountEstimator,
        private readonly StoreManagerInterface $storeManager,
        private readonly DeliveryTimeProviderInterface $deliveryTimeProvider,
        private readonly SalableProductSelector $salableProductSelector,
        private readonly ColorMatcher $colorMatcher
    ) {
    }

    /**
     * The best bundle deal for which the product is an entry, or null.
     *
     * @return array<string,mixed>|null
     */
    public function getForProduct(Product $product, int $customerGroupId): ?array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $key = $product->getId() . '|' . $customerGroupId . '|' . $storeId;
        if (!array_key_exists($key, $this->productCache)) {
            $this->productCache[$key] = $this->findForProduct($product, $customerGroupId, $storeId);
        }

        return $this->productCache[$key];
    }

    /**
     * Builds the best bundle deal for which the product is an entry, or null.
     *
     * @return array<string,mixed>|null
     */
    private function findForProduct(Product $product, int $customerGroupId, int $storeId): ?array
    {
        if (!$this->config->isEnabled($storeId)) {
            return null;
        }
        $rules = $this->activeRuleProvider->getRules($storeId);
        $entries = $rules ? $this->index->getEntrySlotsForProduct((int) $product->getId(), array_keys($rules)) : [];
        foreach ($rules as $ruleId => $rule) {
            if (!isset($entries[$ruleId]) || !$rule->appliesToCustomerGroup($customerGroupId)) {
                continue;
            }
            $data = $this->build($rule, $entries[$ruleId], $product, $customerGroupId, $storeId);
            if ($data !== null) {
                return $data;
            }
        }

        return null;
    }

    /**
     * Cache tags of everything rendered by the last call.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        return array_values(array_unique($this->identities));
    }

    /**
     * Products of the slots that the cart does not fill yet, for every bundle deal the cart has started.
     *
     * @param array<int,int> $cartProductIds
     * @return array<int,array<string,mixed>> list of bundles, each with the missing slots and their products
     */
    public function getMissingForCart(array $cartProductIds, int $customerGroupId, int $limitRules = 2): array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$cartProductIds || !$this->config->isEnabled($storeId)) {
            return [];
        }
        $rules = $this->activeRuleProvider->getRules($storeId);
        $membership = array_intersect_key($this->index->getMembershipForProducts($cartProductIds), $rules);
        $inCart = array_fill_keys(array_map('intval', $cartProductIds), true);
        $limit = $this->config->getMaxCandidates($storeId);

        $result = [];
        foreach ($rules as $ruleId => $rule) {
            if (!isset($membership[$ruleId]) || !$rule->appliesToCustomerGroup($customerGroupId)) {
                continue;
            }
            $missing = [];
            foreach ($this->getSlots($ruleId) as $slot) {
                if (!empty($membership[$ruleId][(int) $slot->getId()])) {
                    continue;
                }
                $candidateIds = array_values(array_filter(
                    $this->index->getSlotProductIds($ruleId, (int) $slot->getId()),
                    static fn (int $id): bool => !isset($inCart[$id])
                ));
                $products = $this->salableProductSelector->select($candidateIds, $storeId, $limit);
                if (!$products) {
                    continue 2;
                }
                $missing[] = $this->buildSlot($slot, $products, false);
            }
            if (!$missing) {
                continue;
            }
            $result[] = [
                'ruleId' => (int) $ruleId,
                'title' => $this->getTitle($rule, $storeId),
                'slots' => $missing,
                'extraDiscounts' => $this->getExtraDiscounts($rule, $storeId, $customerGroupId),
            ];
            if (count($result) >= $limitRules) {
                break;
            }
        }

        return $result;
    }

    /**
     * Active bundle deals with the default product of every slot, for an overview such as a CMS page.
     *
     * The url points to the product page of the first entry product, where the bundle can be configured.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getOverview(int $customerGroupId, int $limit = 10): array
    {
        return $this->getOverviewBundles($customerGroupId, $limit);
    }

    /**
     * Active bundle deals of the current store view with their default products, best priority first.
     *
     * Bundles of which a slot has no salable product are left out, or returned with "available" false and
     * without slots when $includeUnavailable is set, so a colour switch can show them as sold out.
     *
     * @param int $limit 0 for all
     * @param int[] $ruleIds only these rules; empty for all
     * @return array<int,array<string,mixed>>
     */
    public function getOverviewBundles(
        int $customerGroupId,
        int $limit = 0,
        bool $includeUnavailable = false,
        array $ruleIds = []
    ): array {
        $storeId = (int) $this->storeManager->getStore()->getId();
        if (!$this->config->isEnabled($storeId)) {
            return [];
        }

        $result = [];
        $available = 0;
        $rules = $this->activeRuleProvider->getRules($storeId);
        if ($ruleIds) {
            $rules = array_intersect_key($rules, array_flip(array_map('intval', $ruleIds)));
        }
        foreach ($rules as $rule) {
            if (!$rule->appliesToCustomerGroup($customerGroupId)) {
                continue;
            }
            $bundle = $this->buildOverviewBundle($rule, $customerGroupId, $storeId);
            if ($bundle === null && !$includeUnavailable) {
                continue;
            }
            $result[] = $bundle ?? $this->describeRule($rule) + ['available' => false, 'slots' => []];
            if ($bundle !== null && $limit > 0 && ++$available >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * Full storefront data of one bundle deal for its set page, or null when a required slot has no salable product.
     *
     * Optional slots without a salable product are left out; every slot carries all its salable candidates with
     * their configurable options.
     *
     * @return array<string,mixed>|null
     */
    public function getSetBundle(Rule $rule, int $customerGroupId): ?array
    {
        $storeId = (int) $this->storeManager->getStore()->getId();
        $ruleId = (int) $rule->getId();
        if (!$this->config->isEnabled($storeId) || !$rule->appliesToCustomerGroup($customerGroupId)) {
            return null;
        }
        $limit = $this->config->getMaxCandidates($storeId);
        $slots = [];
        foreach ($this->getSlots($ruleId) as $slot) {
            $products = $this->salableProductSelector->select(
                $this->index->getSlotProductIds($ruleId, (int) $slot->getId()),
                $storeId,
                $limit
            );
            if (!$products) {
                if ($slot->isOptional()) {
                    continue;
                }
                return null;
            }
            $data = $this->buildSlot($slot, $products, false);
            $data['isEntry'] = $slot->isEntry();
            $data['slotType'] = $slot->getSlotType();
            $slots[] = $data;
        }
        if (count($slots) < 2) {
            return null;
        }
        $this->identities[] = Rule::CACHE_TAG . '_' . $ruleId;

        return array_merge($this->describeRule($rule), [
            'available' => true,
            'slots' => $slots,
            'delivery' => $this->getDeliveryTimes($slots, $storeId),
        ]);
    }

    /**
     * Builds the overview data of one bundle deal, or null when a slot has no salable product.
     *
     * @return array<string,mixed>|null
     */
    private function buildOverviewBundle(Rule $rule, int $customerGroupId, int $storeId): ?array
    {
        $ruleId = (int) $rule->getId();
        $slots = [];
        $url = null;
        $entryProductId = null;
        foreach ($this->getSlots($ruleId) as $slot) {
            $products = $this->salableProductSelector->select(
                $this->index->getSlotProductIds($ruleId, (int) $slot->getId()),
                $storeId,
                1
            );
            if (!$products) {
                if ($slot->isOptional()) {
                    continue;
                }
                return null;
            }
            $data = $this->buildSlot($slot, [reset($products)], false, false);
            $productId = (int) $data['candidates'][0]['productId'];
            $data['isEntry'] = $slot->isEntry();
            if ($url === null && $slot->isEntry()) {
                $entrySlots = $this->index->getEntrySlotsForProduct($productId, [$ruleId]);
                if (isset($entrySlots[$ruleId])) {
                    $url = $data['candidates'][0]['url'];
                    $entryProductId = $productId;
                }
            }
            $slots[] = $data;
        }
        if (count($slots) < 2) {
            return null;
        }
        $this->identities[] = Rule::CACHE_TAG . '_' . $ruleId;

        return array_merge($this->describeRule($rule), [
            'available' => true,
            'url' => $url ?? $slots[0]['candidates'][0]['url'],
            'entryProductId' => $entryProductId ?? (int) $slots[0]['candidates'][0]['productId'],
            'slots' => $slots,
            'extraDiscounts' => $this->getExtraDiscounts($rule, $storeId, $customerGroupId),
        ]);
    }

    /**
     * Rule level data shared by overview bundles.
     *
     * @return array<string,mixed>
     */
    private function describeRule(Rule $rule): array
    {
        return [
            'ruleId' => (int) $rule->getId(),
            'title' => (string) ($rule->getTitle() ?: $rule->getName()),
            'name' => (string) $rule->getName(),
            'urlKey' => (string) $rule->getUrlKey(),
            'priority' => $rule->getPriority(),
            'colorGroup' => $rule->getColorGroup(),
            'colorOptionId' => $rule->getColorOptionId(),
            'colorSortOrder' => $rule->getColorSortOrder(),
            'url' => null,
            'entryProductId' => null,
        ];
    }

    /**
     * Builds the storefront data of one bundle deal for an entry product.
     *
     * @return array<string,mixed>|null
     */
    private function build(Rule $rule, int $entrySlotId, Product $product, int $customerGroupId, int $storeId): ?array
    {
        $ruleId = (int) $rule->getId();
        $maxOtherSlots = $this->config->getMaxSlots($storeId);
        $limit = $this->config->getMaxCandidates($storeId);

        $slots = [];
        $others = 0;
        foreach ($this->getSlots($ruleId) as $slot) {
            $slotId = (int) $slot->getId();
            if ($slotId === $entrySlotId) {
                $slots[] = $this->buildSlot($slot, [(int) $product->getId() => $product], true);
                continue;
            }
            if ($others >= $maxOtherSlots) {
                // A bundle with more members than the block shows cannot be completed from this page.
                return null;
            }
            $candidateIds = array_values(array_diff(
                $this->index->getSlotProductIds($ruleId, $slotId),
                [(int) $product->getId()]
            ));
            // The item in the colour of the viewed product goes first, before the limit cuts the list.
            $candidateIds = $this->colorMatcher->sortCandidateIds($candidateIds, (int) $product->getId(), $storeId);
            $products = $this->salableProductSelector->select($candidateIds, $storeId, $limit);
            if (!$products) {
                return null;
            }
            $slots[] = $this->buildSlot($slot, $products, false);
            $others++;
        }
        if ($others === 0) {
            return null;
        }

        $this->identities[] = Rule::CACHE_TAG . '_' . $ruleId;

        return [
            'ruleId' => $ruleId,
            'title' => $this->getTitle($rule, $storeId),
            'slots' => $slots,
            'extraDiscounts' => $this->getExtraDiscounts($rule, $storeId, $customerGroupId),
            'delivery' => (object) $this->getDeliveryTimes($slots, $storeId),
            'showSplitShipment' => $this->config->isSplitShipmentShown($storeId),
        ];
    }

    /**
     * Delivery times of all candidates and their configurable children, keyed by product id.
     *
     * @param array<int,array<string,mixed>> $slots
     * @return array<int,array{label:string, minDays:int|null, maxDays:int|null, direct:bool}>
     */
    private function getDeliveryTimes(array $slots, int $storeId): array
    {
        $productIds = [];
        foreach ($slots as $slot) {
            foreach ($slot['candidates'] as $candidate) {
                $productIds[] = (int) $candidate['productId'];
                if (!empty($candidate['options']['index'])) {
                    foreach (array_keys((array) $candidate['options']['index']) as $childId) {
                        $productIds[] = (int) $childId;
                    }
                }
            }
        }

        return $this->deliveryTimeProvider->getForProductIds(array_values(array_unique($productIds)), $storeId);
    }

    /**
     * Builds the storefront data of one slot.
     *
     * @param Product[] $products
     * @return array<string,mixed>
     */
    private function buildSlot(Slot $slot, array $products, bool $isCurrent, bool $withOptions = true): array
    {
        $candidates = [];
        foreach ($products as $product) {
            $candidates[] = $this->productDataBuilder->build($product, $withOptions);
            foreach ($product->getIdentities() as $tag) {
                $this->identities[] = $tag;
            }
        }

        return [
            'slotId' => (int) $slot->getId(),
            'title' => (string) $slot->getTitle(),
            'isCurrent' => $isCurrent,
            'discount' => [
                'type' => $slot->getDiscountType(),
                'amount' => $slot->getDiscountAmount(),
                'excludeSpecialPrice' => $slot->isSpecialPriceExcluded(),
            ],
            'candidates' => $candidates,
        ];
    }

    /**
     * Returns the slots of a bundle deal.
     *
     * @return Slot[]
     */
    private function getSlots(int $ruleId): array
    {
        /**
         * @var Slot[] $slots
         */
        $slots = $this->slotCollectionFactory->create()->addRuleFilter($ruleId)->getItems();

        return array_values($slots);
    }

    private function getTitle(Rule $rule, int $storeId): string
    {
        return (string) ($rule->getTitle() ?: $this->config->getBlockTitle($storeId));
    }

    /**
     * Returns the other discounts that apply on top of the bundle deal; none when the deal excludes them.
     *
     * @return array<int,array{type:string, amount:float, stop:bool}>
     */
    private function getExtraDiscounts(Rule $rule, int $storeId, int $customerGroupId): array
    {
        if ($rule->isExclusive()) {
            return [];
        }
        $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();

        return $this->discountEstimator->getDiscounts((int) $rule->getId(), $websiteId, $customerGroupId);
    }
}
