<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\ViewModel;

use FalconMedia\BundleDeals\Model\Config\Source\OverviewSort;
use FalconMedia\BundleDeals\Model\Overview\CardBuilder;
use FalconMedia\BundleDeals\Model\Overview\Config as OverviewConfig;
use FalconMedia\BundleDeals\Model\Page\Config;
use FalconMedia\BundleDeals\Model\Page\ReviewProvider;
use FalconMedia\BundleDeals\Model\ResourceModel\ProductAttributeValues;
use FalconMedia\BundleDeals\Model\Rule;
use FalconMedia\BundleDeals\Model\Set\ActiveRuleProvider;
use FalconMedia\BundleDeals\Model\Storefront\BundleDataProvider;
use FalconMedia\BundleDeals\Model\Storefront\ColorGroupProvider;
use Magento\Cms\Block\Block as CmsBlock;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Group;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Data of the set page /<prefix>/<url key>: the requested deal, the other colours of its colour group,
 * reviews, more sets and structured data.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class SetPage implements ArgumentInterface
{
    public const PARAM_KEY = 'set_key';

    /**
     * @var array<string,mixed>|null
     */
    private ?array $data = null;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        private readonly Config $config,
        private readonly OverviewConfig $overviewConfig,
        private readonly ActiveRuleProvider $activeRuleProvider,
        private readonly BundleDataProvider $dataProvider,
        private readonly ColorGroupProvider $colorGroupProvider,
        private readonly CardBuilder $cardBuilder,
        private readonly ReviewProvider $reviewProvider,
        private readonly ProductAttributeValues $attributeValues,
        private readonly HttpContext $httpContext,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly UrlInterface $url,
        private readonly LayoutInterface $layout,
        private readonly Json $json
    ) {
    }

    /**
     * Tells whether the requested set page can be served; false means the controller must answer 404.
     */
    public function isAvailable(): bool
    {
        return $this->getData() !== [];
    }

    public function isIndexable(): bool
    {
        return $this->config->isIndexable($this->getStoreId());
    }

    /**
     * Returns the id of the requested deal, 0 when the page is not available.
     */
    public function getRuleId(): int
    {
        return (int) ($this->getData()['ruleId'] ?? 0);
    }

    public function getTitle(): string
    {
        return (string) ($this->getData()['title'] ?? '');
    }

    /**
     * Returns the brand label of the entry product, empty when unknown.
     */
    public function getBrand(): string
    {
        return (string) ($this->getData()['brand'] ?? '');
    }

    public function getDescription(): string
    {
        return (string) ($this->getData()['description'] ?? '');
    }

    public function getCanonicalUrl(): string
    {
        return (string) ($this->getData()['url'] ?? '');
    }

    /**
     * Returns the meta description: the admin description or a generated sentence.
     */
    public function getMetaDescription(): string
    {
        $description = trim(preg_replace('/\s+/', ' ', strip_tags($this->getDescription())) ?? '');
        if ($description === '') {
            $variant = $this->getCurrentVariant();
            $description = (string) __(
                '%1: a set of %2 items for %3 instead of %4.',
                $this->getTitle(),
                count($variant['items'] ?? []),
                $this->formatPrice((float) ($variant['setPrice'] ?? 0)),
                $this->formatPrice((float) ($variant['sumPrice'] ?? 0))
            );
        }

        return mb_strlen($description) > 160 ? mb_substr($description, 0, 157) . '...' : $description;
    }

    /**
     * Returns the configuration of the Alpine component.
     *
     * @return array<string,mixed>
     */
    public function getComponentConfig(): array
    {
        $data = $this->getData();
        $variants = [];
        foreach ($data['variants'] ?? [] as $variant) {
            $variants[] = $variant;
        }

        return [
            'ruleId' => $this->getRuleId(),
            'variants' => $variants,
            'addUrl' => $this->url->getUrl('bundledeals/cart/add'),
            'sticky' => $this->config->isStickyBar($this->getStoreId()),
            'i18n' => $this->getI18n(),
        ];
    }

    /**
     * Returns the item list of the requested deal for the server rendered fallback.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getItems(): array
    {
        return (array) ($this->getCurrentVariant()['items'] ?? []);
    }

    /**
     * Returns the colour variants with their set page URLs, for links that also work without JavaScript.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getVariants(): array
    {
        return (array) ($this->getData()['variants'] ?? []);
    }

    /**
     * Returns the totals of the requested deal: sumPrice, setPrice, saving.
     *
     * @return array<string,float>
     */
    public function getTotals(): array
    {
        $variant = $this->getCurrentVariant();

        return [
            'sumPrice' => (float) ($variant['sumPrice'] ?? 0),
            'setPrice' => (float) ($variant['setPrice'] ?? 0),
            'saving' => (float) ($variant['saving'] ?? 0),
        ];
    }

    /**
     * Returns the review summary of the set items.
     *
     * @return array{count: int, average: float}
     */
    public function getReviewSummary(): array
    {
        return (array) ($this->getData()['reviewSummary'] ?? ['count' => 0, 'average' => 0.0]);
    }

    /**
     * Returns the latest reviews of the set items, with the name of the reviewed item.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getReviews(): array
    {
        return (array) ($this->getData()['reviews'] ?? []);
    }

    /**
     * Returns the cards of other sets for the "more sets" section.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getMoreSets(): array
    {
        return (array) ($this->getData()['moreSets'] ?? []);
    }

    /**
     * Returns the delivery or return text and CMS block settings of the accordions.
     *
     * @return array<string,string>
     */
    public function getInfoSettings(): array
    {
        $storeId = $this->getStoreId();

        return [
            'deliveryText' => $this->config->getDeliveryText($storeId),
            'deliveryBlock' => $this->config->getDeliveryCmsBlock($storeId),
            'returnText' => $this->config->getReturnText($storeId),
            'returnBlock' => $this->config->getReturnCmsBlock($storeId),
            'sizeBlock' => $this->config->getSizeChartCmsBlock($storeId),
        ];
    }

    /**
     * Renders a CMS block by identifier, empty when it does not exist or is disabled.
     */
    public function getCmsBlockHtml(string $identifier): string
    {
        if ($identifier === '') {
            return '';
        }
        /**
         * @var CmsBlock $block
         */
        $block = $this->layout->createBlock(CmsBlock::class);

        return (string) $block->setBlockId($identifier)->toHtml();
    }

    /**
     * Returns the Product and BreadcrumbList structured data as one JSON string.
     */
    public function getJsonLd(): string
    {
        $variant = $this->getCurrentVariant();
        $images = array_slice((array) ($variant['images'] ?? []), 0, 5);
        $parts = [];
        foreach ((array) ($variant['items'] ?? []) as $item) {
            $candidate = $item['candidates'][0];
            $parts[] = array_filter([
                '@type' => 'Product',
                'name' => $candidate['name'],
                'image' => $candidate['image'] ?: null,
                'url' => $candidate['url'] ?: null,
            ]);
        }
        $product = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $this->getTitle(),
            'description' => $this->getMetaDescription(),
            'image' => $images,
            'url' => $this->getCanonicalUrl(),
            'hasPart' => $parts,
            'offers' => [
                '@type' => 'Offer',
                'url' => $this->getCanonicalUrl(),
                'priceCurrency' => $this->storeManager->getStore()->getCurrentCurrencyCode(),
                'price' => number_format((float) ($variant['setPrice'] ?? 0), 2, '.', ''),
                'availability' => 'https://schema.org/InStock',
            ],
        ];
        if ($this->getBrand() !== '') {
            $product['brand'] = ['@type' => 'Brand', 'name' => $this->getBrand()];
        }
        $summary = $this->getReviewSummary();
        if ($summary['count'] > 0) {
            $product['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => $summary['average'],
                'reviewCount' => $summary['count'],
            ];
        }

        $crumbs = $this->getBreadcrumbs();
        $crumbs[count($crumbs) - 1]['url'] = $this->getCanonicalUrl();
        $list = [];
        foreach ($crumbs as $index => $crumb) {
            $list[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ];
        }

        // Escaped so that admin text cannot open a comment or close the script element.
        return str_replace('<', '\\u003c', $this->json->serialize([
            $product,
            ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list],
        ]));
    }

    /**
     * Returns the breadcrumb trail as name and URL pairs, the last one without URL.
     *
     * @return array<int,array{name: string, url: string|null}>
     */
    public function getBreadcrumbs(): array
    {
        $crumbs = [['name' => (string) __('Home'), 'url' => $this->storeManager->getStore()->getBaseUrl()]];
        if ($this->overviewConfig->isEnabled($this->getStoreId())) {
            $crumbs[] = [
                'name' => $this->overviewConfig->getH1($this->getStoreId()),
                'url' => $this->url->getUrl('', ['_direct' => $this->overviewConfig->getUrlKey($this->getStoreId())]),
            ];
        }
        $crumbs[] = ['name' => $this->getTitle(), 'url' => null];

        return $crumbs;
    }

    public function formatPrice(float $amount): string
    {
        return $this->priceCurrency->format($amount, false);
    }

    /**
     * Cache tags of the rendered set.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        $this->getData();

        return array_merge([Rule::CACHE_TAG], $this->dataProvider->getIdentities());
    }

    /**
     * Builds the page data once; an empty array means the page is not available.
     *
     * @return array<string,mixed>
     */
    private function getData(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $this->data = [];
        $storeId = $this->getStoreId();
        $key = strtolower(trim((string) $this->request->getParam(self::PARAM_KEY)));
        if ($key === '' || !$this->config->isEnabled($storeId)) {
            return $this->data;
        }
        $groupId = $this->getCustomerGroupId();
        $rule = null;
        foreach ($this->activeRuleProvider->getRules($storeId) as $candidate) {
            if (strtolower((string) $candidate->getUrlKey()) === $key) {
                $rule = $candidate;
                break;
            }
        }
        if ($rule === null) {
            return $this->data;
        }
        $variants = $this->buildVariants($rule, $groupId, $storeId);
        $current = null;
        foreach ($variants as $variant) {
            if ($variant['ruleId'] === (int) $rule->getId()) {
                $current = $variant;
            }
        }
        if ($current === null || !$current['available']) {
            return $this->data;
        }

        $productIds = [];
        $names = [];
        foreach ($current['items'] as $item) {
            foreach ($item['candidates'] as $candidate) {
                $productIds[] = $candidate['productId'];
                $names[$candidate['productId']] = $candidate['name'];
            }
        }
        $reviews = [];
        $summary = ['count' => 0, 'average' => 0.0];
        if ($this->config->getReviewSource($storeId) === Config::REVIEWS_ITEMS) {
            $summary = $this->reviewProvider->getSummary($productIds, $storeId);
            foreach ($this->reviewProvider->getLatest($productIds, $storeId) as $review) {
                $reviews[] = $review + ['productName' => $names[$review['productId']] ?? ''];
            }
        }

        return $this->data = [
            'ruleId' => (int) $rule->getId(),
            'title' => (string) ($rule->getTitle() ?: $rule->getName()),
            'description' => $rule->getDescription(),
            'url' => $this->getSetUrl($rule),
            'brand' => $this->getBrandLabel((int) ($current['entryProductId'] ?? 0), $storeId),
            'variants' => $variants,
            'reviewSummary' => $summary,
            'reviews' => $reviews,
            'moreSets' => $this->buildMoreSets($rule, $groupId, $storeId),
        ];
    }

    /**
     * Builds the colour variants of the deal's colour group; a deal without group has one variant.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildVariants(Rule $rule, int $groupId, int $storeId): array
    {
        $members = [(int) $rule->getId() => $rule];
        $group = (string) $rule->getColorGroup();
        if ($group !== '') {
            foreach ($this->activeRuleProvider->getRules($storeId) as $id => $member) {
                if ((string) $member->getColorGroup() === $group) {
                    $members[(int) $id] = $member;
                }
            }
        }
        $labels = [];
        foreach ($this->colorGroupProvider->getVariantsForRule((int) $rule->getId(), $groupId) as $variant) {
            $labels[(int) $variant['ruleId']] = $variant;
        }
        uasort($members, static fn (Rule $a, Rule $b): int => [$a->getColorSortOrder(), $a->getId()]
            <=> [$b->getColorSortOrder(), $b->getId()]);

        $variants = [];
        foreach ($members as $ruleId => $member) {
            $bundle = $member->getUrlKey() !== null && $member->getUrlKey() !== ''
                ? $this->dataProvider->getSetBundle($member, $groupId)
                : null;
            $meta = $labels[$ruleId] ?? [];
            $variant = [
                'ruleId' => $ruleId,
                'label' => (string) ($meta['label'] ?? ''),
                'swatchHex' => $meta['swatchHex'] ?? null,
                'imageUrl' => $meta['imageUrl'] ?? null,
                'url' => $this->getSetUrl($member),
                'available' => $bundle !== null,
                'items' => [],
                'images' => [],
                'delivery' => (object) [],
                'entryProductId' => 0,
                'setPrice' => 0.0,
                'sumPrice' => 0.0,
                'saving' => 0.0,
            ];
            if ($bundle !== null) {
                $variant['items'] = $this->buildItems($bundle);
                $variant['images'] = array_values(array_filter(array_map(
                    static fn (array $item): string => (string) ($item['candidates'][0]['imageLarge']
                        ?? $item['candidates'][0]['image']),
                    $variant['items']
                )));
                $variant['delivery'] = (object) $bundle['delivery'];
                foreach ($bundle['slots'] as $slot) {
                    if (!empty($slot['isEntry'])) {
                        $variant['entryProductId'] = (int) $slot['candidates'][0]['productId'];
                    }
                }
                $variant = array_merge($variant, array_intersect_key(
                    $this->cardBuilder->getTotals($bundle),
                    array_flip(['setPrice', 'sumPrice', 'saving'])
                ));
            }
            $variants[] = $variant;
        }

        return $variants;
    }

    /**
     * Converts the slots of a bundle into the items of a variant.
     *
     * @param array<string,mixed> $bundle
     * @return array<int,array<string,mixed>>
     */
    private function buildItems(array $bundle): array
    {
        $items = [];
        foreach ($bundle['slots'] as $slot) {
            $type = (string) $slot['slotType'];
            $candidates = $slot['candidates'];
            if ($type !== 'variable') {
                // Fixed and optional items always use the first candidate; a shopper only picks the size.
                $candidates = [$candidates[0]];
            }
            $items[] = [
                'slotId' => (int) $slot['slotId'],
                'type' => $type,
                'title' => (string) $slot['title'],
                'discount' => $slot['discount'],
                'candidates' => $candidates,
            ];
        }

        return $items;
    }

    /**
     * Builds cards of other sets, one per colour group, linking to their set pages.
     *
     * @return array<int,array<string,mixed>>
     */
    private function buildMoreSets(Rule $rule, int $groupId, int $storeId): array
    {
        $limit = $this->config->getMoreSetsCount($storeId);
        if ($limit < 1) {
            return [];
        }
        $bundles = [];
        $ownGroup = (string) $rule->getColorGroup();
        $rulesById = $this->activeRuleProvider->getRules($storeId);
        foreach ($this->dataProvider->getOverviewBundles($groupId) as $bundle) {
            $other = $rulesById[(int) $bundle['ruleId']] ?? null;
            if ($other === null || $other->getUrlKey() === null || $other->getUrlKey() === '') {
                continue;
            }
            if ((int) $bundle['ruleId'] === (int) $rule->getId()
                || ($ownGroup !== '' && (string) $bundle['colorGroup'] === $ownGroup)
            ) {
                continue;
            }
            $bundle['url'] = $this->getSetUrl($other);
            $bundles[] = $bundle;
        }
        $cards = $this->cardBuilder->sortCards($this->cardBuilder->buildCards($bundles, []), OverviewSort::SAVING);

        return array_slice($cards, 0, $limit);
    }

    private function getBrandLabel(int $entryProductId, int $storeId): string
    {
        if ($entryProductId < 1) {
            return '';
        }
        $attribute = $this->overviewConfig->getBrandAttribute($storeId);
        $optionId = $this->attributeValues->getOptionIds($attribute, [$entryProductId], $storeId)[$entryProductId][0]
            ?? null;

        return $optionId !== null
            ? (string) ($this->attributeValues->getOptionLabels($attribute, $storeId)[$optionId] ?? '')
            : '';
    }

    private function getSetUrl(Rule $rule): string
    {
        $storeId = $this->getStoreId();

        return $this->url->getUrl('', [
            '_direct' => $this->config->getUrlPrefix($storeId) . '/' . $rule->getUrlKey(),
            '_nosid' => true,
        ]);
    }

    /**
     * Returns the variant of the requested deal.
     *
     * @return array<string,mixed>
     */
    private function getCurrentVariant(): array
    {
        foreach ($this->getVariants() as $variant) {
            if ($variant['ruleId'] === $this->getRuleId()) {
                return $variant;
            }
        }

        return [];
    }

    /**
     * Returns the texts the Alpine component needs.
     *
     * @return array<string,string>
     */
    private function getI18n(): array
    {
        return [
            'chooseSizes' => (string) __('Choose your size for %1 items'),
            'chooseSizesOne' => (string) __('Choose your size for 1 item'),
            'addSet' => (string) __('Add set - %1'),
            'allTomorrow' => (string) __('Everything in house tomorrow'),
            'completeRange' => (string) __('Delivered complete in %1 to %2 business days'),
            'completeDays' => (string) __('Delivered complete in %1 business days'),
            'completeLater' => (string) __('Delivered complete once every item is in stock'),
            'chooseSizesFirst' => (string) __('Choose your sizes first'),
            'savePercent' => (string) __('-%1% on the set'),
            'setOf' => (string) __('Set of %1'),
            'colourLabel' => (string) __('Colour %1'),
            'unavailable' => (string) __('Not available'),
            'optional' => (string) __('Optional'),
            'include' => (string) __('Include in the set'),
            'error' => (string) __('We can\'t add this bundle deal to your shopping cart right now.'),
            'added' => (string) __('The set has been added to your shopping cart.'),
            'size' => (string) __('Size'),
        ];
    }

    private function getCustomerGroupId(): int
    {
        $groupId = $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);

        return $groupId === null ? Group::NOT_LOGGED_IN_ID : (int) $groupId;
    }

    private function getStoreId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }
}
