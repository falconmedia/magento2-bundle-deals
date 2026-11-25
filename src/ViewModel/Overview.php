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
use FalconMedia\BundleDeals\Model\Import\Amasty\ColorGroupResolver;
use FalconMedia\BundleDeals\Model\Overview\CardBuilder;
use FalconMedia\BundleDeals\Model\Overview\Config;
use FalconMedia\BundleDeals\Model\Overview\UrlBuilder;
use FalconMedia\BundleDeals\Model\Page\Config as PageConfig;
use FalconMedia\BundleDeals\Model\ResourceModel\ProductAttributeValues;
use FalconMedia\BundleDeals\Model\Storefront\BundleDataProvider;
use FalconMedia\BundleDeals\Model\Storefront\ColorGroupProvider;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Group;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Data of the bundle deal overview page: filtered, sorted and paged cards, filter options and pager links.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Overview implements ArgumentInterface
{
    public const PARAM_SPORT = 'sport';
    public const PARAM_SORT = 'sort';
    public const PARAM_PAGE = 'p';

    /**
     * @var PageConfig
     */
    private $pageConfig;

    /**
     * @var UrlInterface
     */
    private $url;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var UrlBuilder
     */
    private $urlBuilder;

    /**
     * @var BundleDataProvider
     */
    private $dataProvider;

    /**
     * @var ColorGroupProvider
     */
    private $colorGroupProvider;

    /**
     * @var CardBuilder
     */
    private $cardBuilder;

    /**
     * @var ProductAttributeValues
     */
    private $attributeValues;

    /**
     * @var ColorGroupResolver
     */
    private $slugger;

    /**
     * @var OverviewSort
     */
    private $sortSource;

    /**
     * @var HttpContext
     */
    private $httpContext;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var PriceCurrencyInterface
     */
    private $priceCurrency;

    /**
     * @var Json
     */
    private $json;

    /**
     * @var array<int,array<string,mixed>>|null
     */
    private $cards;

    /**
     * @var array<int,array<string,mixed>>|null
     */
    private $sortedCards;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Config $config,
        UrlBuilder $urlBuilder,
        BundleDataProvider $dataProvider,
        ColorGroupProvider $colorGroupProvider,
        CardBuilder $cardBuilder,
        ProductAttributeValues $attributeValues,
        ColorGroupResolver $slugger,
        OverviewSort $sortSource,
        HttpContext $httpContext,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        PriceCurrencyInterface $priceCurrency,
        Json $json,
        PageConfig $pageConfig,
        UrlInterface $url
    ) {
        $this->pageConfig = $pageConfig;
        $this->url = $url;
        $this->config = $config;
        $this->urlBuilder = $urlBuilder;
        $this->dataProvider = $dataProvider;
        $this->colorGroupProvider = $colorGroupProvider;
        $this->cardBuilder = $cardBuilder;
        $this->attributeValues = $attributeValues;
        $this->slugger = $slugger;
        $this->sortSource = $sortSource;
        $this->httpContext = $httpContext;
        $this->request = $request;
        $this->storeManager = $storeManager;
        $this->priceCurrency = $priceCurrency;
        $this->json = $json;
    }

    /**
     * Active filters as slugs, keyed by CardBuilder filter code.
     *
     * @return array<string,string>
     */
    public function getFilters(): array
    {
        $brand = $this->getScalarParam($this->getBrandParam());

        return array_filter([
            CardBuilder::FILTER_SPORT => $this->cleanSlug($this->getScalarParam(self::PARAM_SPORT)),
            CardBuilder::FILTER_BRAND => $this->cleanSlug($brand),
        ]);
    }

    /**
     * Name of the URL parameter of the brand filter, from the configuration.
     */
    public function getBrandParam(): string
    {
        return $this->config->getBrandParam();
    }

    /**
     * Current sort code; unknown values fall back to the configured default.
     */
    public function getSort(): string
    {
        $sort = $this->getScalarParam(self::PARAM_SORT);

        return in_array($sort, $this->sortSource->getCodes(), true) ? $sort : $this->config->getDefaultSort();
    }

    /**
     * Current page number, at least 1.
     */
    public function getPage(): int
    {
        return max(1, (int) $this->getScalarParam(self::PARAM_PAGE));
    }

    /**
     * Tells whether the request carries filter or sort parameters, which makes the page non indexable.
     */
    public function hasFilterParams(): bool
    {
        foreach ([self::PARAM_SPORT, $this->getBrandParam(), self::PARAM_SORT] as $param) {
            if ($this->getScalarParam($param) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Canonical URL: the overview URL, with the page number from page 2 on.
     */
    public function getCanonicalUrl(): string
    {
        $page = $this->getPager()['page'];

        return $this->urlBuilder->getOverviewUrl($page > 1 ? [self::PARAM_PAGE => $page] : []);
    }

    /**
     * Cards of the current page.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getCards(): array
    {
        $pager = $this->getPager();

        return array_slice($this->getSortedCards(), ($pager['page'] - 1) * $pager['perPage'], $pager['perPage']);
    }

    /**
     * Pager state with previous and next page URLs.
     *
     * @return array{page:int, pages:int, perPage:int, total:int, prevUrl:string|null, nextUrl:string|null}
     */
    public function getPager(): array
    {
        $perPage = $this->config->getItemsPerPage();
        $total = count($this->getSortedCards());
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min($this->getPage(), $pages);

        return [
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'total' => $total,
            'prevUrl' => $page > 1 ? $this->getUrl([self::PARAM_PAGE => $page - 1]) : null,
            'nextUrl' => $page < $pages ? $this->getUrl([self::PARAM_PAGE => $page + 1]) : null,
        ];
    }

    /**
     * Numbered page links around the current page; a null page marks a gap.
     *
     * @return array<int,array{page:int|null, url:string|null, current:bool}>
     */
    public function getPageLinks(): array
    {
        $pager = $this->getPager();
        $links = [];
        $previous = 0;
        for ($page = 1; $page <= $pager['pages']; $page++) {
            $nearCurrent = abs($page - $pager['page']) <= 1;
            if ($page !== 1 && $page !== $pager['pages'] && !$nearCurrent) {
                continue;
            }
            if ($page - $previous > 1) {
                $links[] = ['page' => null, 'url' => null, 'current' => false];
            }
            $links[] = [
                'page' => $page,
                'url' => $this->getUrl([self::PARAM_PAGE => $page > 1 ? $page : null]),
                'current' => $page === $pager['page'],
            ];
            $previous = $page;
        }

        return $links;
    }

    /**
     * Client state of a colour variant on an overview card, as JSON for the colour switch.
     *
     * @param array<string,mixed> $variant
     */
    public function getVariantState(array $variant): string
    {
        $price = function (string $key) use ($variant): string {
            return isset($variant[$key]) ? $this->formatPrice((float) $variant[$key]) : '';
        };
        $saving = (float) ($variant['saving'] ?? 0);

        return $this->json->serialize([
            'url' => (string) ($variant['url'] ?? ''),
            'images' => array_values(array_map('strval', (array) ($variant['images'] ?? []))),
            'set' => $price('setPrice'),
            'sum' => $price('sumPrice'),
            'save' => $saving > 0 ? (string) __('You save %1', $this->formatPrice($saving)) : '',
            'delivery' => (string) ($variant['deliveryText'] ?? ''),
            'direct' => !empty($variant['deliveryDirect']),
            'colour' => (string) __('Colour: %1', (string) $variant['label']),
        ]);
    }

    /**
     * Overview URL without filter, sort and page parameters.
     */
    public function getResetUrl(): string
    {
        return $this->urlBuilder->getOverviewUrl();
    }

    /**
     * Filter options with counts, URL and active state, keyed by filter code and slug.
     *
     * @return array<string,array<string,array{label:string, count:int, url:string, active:bool}>>
     */
    public function getFacets(): array
    {
        $filters = $this->getFilters();
        $facets = $this->cardBuilder->getFacets($this->getAllCards(), $filters);
        foreach ($facets as $code => $options) {
            $param = $code === CardBuilder::FILTER_SPORT ? self::PARAM_SPORT : $this->getBrandParam();
            foreach ($options as $slug => $option) {
                $active = ($filters[$code] ?? '') === (string) $slug;
                $facets[$code][$slug] = $option + [
                    'url' => $this->getUrl([$param => $active ? null : (string) $slug, self::PARAM_PAGE => null]),
                    'active' => $active,
                ];
            }
        }

        return $facets;
    }

    /**
     * Sort options with label, URL and active state.
     *
     * @return array<int,array{code:string, label:string, url:string, active:bool}>
     */
    public function getSortOptions(): array
    {
        $current = $this->getSort();
        $default = $this->config->getDefaultSort();
        $options = [];
        foreach ($this->sortSource->toOptionArray() as $option) {
            $code = (string) $option['value'];
            $options[] = [
                'code' => $code,
                'label' => (string) $option['label'],
                'url' => $this->getUrl([
                    self::PARAM_SORT => $code === $default ? null : $code,
                    self::PARAM_PAGE => null,
                ]),
                'active' => $code === $current,
            ];
        }

        return $options;
    }

    /**
     * Overview URL with the current parameters, overridden by the given ones; null removes a parameter.
     *
     * @param array<string,mixed> $override
     */
    public function getUrl(array $override = []): string
    {
        $filters = $this->getFilters();
        $sort = $this->getSort();
        $params = [
            self::PARAM_SPORT => $filters[CardBuilder::FILTER_SPORT] ?? null,
            $this->getBrandParam() => $filters[CardBuilder::FILTER_BRAND] ?? null,
            self::PARAM_SORT => $sort !== $this->config->getDefaultSort() ? $sort : null,
            self::PARAM_PAGE => $this->getPage(),
        ];

        return $this->urlBuilder->getOverviewUrl(array_merge($params, $override));
    }

    /**
     * Page H1.
     */
    public function getHeading(): string
    {
        return $this->config->getH1();
    }

    public function getIntro(): string
    {
        return $this->config->getIntro();
    }

    /**
     * Identifier of the CMS block shown on the page, empty for none.
     */
    public function getCmsBlockId(): string
    {
        return $this->config->getCmsBlock();
    }

    public function formatPrice(float $amount): string
    {
        return $this->priceCurrency->format($amount, false);
    }

    /**
     * ItemList structured data of the cards on the current page.
     */
    public function getJsonLd(): string
    {
        $items = [];
        $offset = ($this->getPager()['page'] - 1) * $this->getPager()['perPage'];
        foreach ($this->getCards() as $index => $card) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $offset + $index + 1,
                'url' => $card['url'],
                'name' => $card['title'],
            ];
        }

        // Escaped so that admin text cannot open a comment or close the script element.
        return str_replace('<', '\\u003c', $this->json->serialize([
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $this->config->getH1(),
            'numberOfItems' => count($items),
            'itemListElement' => $items,
        ]));
    }

    /**
     * Cache tags of the rendered bundle deals.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        $this->getAllCards();

        return $this->dataProvider->getIdentities();
    }

    /**
     * Filtered and sorted cards of all pages.
     *
     * @return array<int,array<string,mixed>>
     */
    private function getSortedCards(): array
    {
        if ($this->sortedCards === null) {
            $this->sortedCards = $this->cardBuilder->sortCards(
                $this->cardBuilder->filterCards($this->getAllCards(), $this->getFilters()),
                $this->getSort()
            );
        }

        return $this->sortedCards;
    }

    /**
     * All cards before filtering.
     *
     * @return array<int,array<string,mixed>>
     */
    private function getAllCards(): array
    {
        if ($this->cards !== null) {
            return $this->cards;
        }
        $storeId = (int) $this->storeManager->getStore()->getId();
        $groupId = $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);
        $bundles = $this->dataProvider->getOverviewBundles(
            $groupId === null ? Group::NOT_LOGGED_IN_ID : (int) $groupId,
            0,
            true
        );

        $entryIds = [];
        $slotProductIds = [];
        foreach ($bundles as $index => $bundle) {
            $bundles[$index]['url'] = $this->getSetUrl($bundle) ?? $bundle['url'];
            if (!empty($bundle['entryProductId'])) {
                $entryIds[] = (int) $bundle['entryProductId'];
            }
            foreach ((array) $bundle['slots'] as $slot) {
                $slotProductIds[] = (int) $slot['candidates'][0]['productId'];
            }
        }
        $deliveries = $this->colorGroupProvider->getDeliveries($entryIds, $storeId);
        $deliveryTexts = array_map(static fn (array $delivery): string => $delivery['label'], $deliveries);
        $deliveryDirect = array_map(static fn (array $delivery): bool => $delivery['direct'], $deliveries);
        $meta = $this->buildMeta($bundles, $entryIds, $slotProductIds, $storeId, $deliveryTexts, $deliveryDirect);

        return $this->cards = $this->cardBuilder->buildCards(
            $bundles,
            $meta,
            $this->colorGroupProvider->getVariantsByGroup($bundles, $storeId, $deliveryTexts, $deliveryDirect)
        );
    }

    /**
     * Set page URL of a bundle deal, or null when the deal has no URL key.
     *
     * @param array<string,mixed> $bundle
     */
    private function getSetUrl(array $bundle): ?string
    {
        $urlKey = (string) ($bundle['urlKey'] ?? '');
        if ($urlKey === '') {
            return null;
        }

        return $this->url->getUrl('', [
            '_direct' => $this->pageConfig->getUrlPrefix((int) $this->storeManager->getStore()->getId()) . '/' . $urlKey,
            '_nosid' => true,
        ]);
    }

    /**
     * Brand, sports and delivery text per rule id.
     *
     * @param array<int,array<string,mixed>> $bundles
     * @param int[] $entryIds
     * @param int[] $slotProductIds
     * @param array<int,string> $deliveryTexts
     * @param array<int,bool> $deliveryDirect
     * @return array<int,array<string,mixed>>
     */
    private function buildMeta(
        array $bundles,
        array $entryIds,
        array $slotProductIds,
        int $storeId,
        array $deliveryTexts,
        array $deliveryDirect
    ): array {
        $brandAttribute = $this->config->getBrandAttribute();
        $sportAttribute = $this->config->getSportAttribute();
        $brandOptions = $this->attributeValues->getOptionIds($brandAttribute, $entryIds, $storeId);
        $brandLabels = $this->attributeValues->getOptionLabels($brandAttribute, $storeId);
        $sportOptions = $this->attributeValues->getOptionIds(
            $sportAttribute,
            array_values(array_unique(array_merge($entryIds, $slotProductIds))),
            $storeId
        );
        $sportLabels = $this->attributeValues->getOptionLabels($sportAttribute, $storeId);

        $meta = [];
        foreach ($bundles as $bundle) {
            if (empty($bundle['available'])) {
                continue;
            }
            $entryId = (int) $bundle['entryProductId'];
            $brandOptionId = $brandOptions[$entryId][0] ?? null;
            $brand = $brandOptionId !== null ? ($brandLabels[$brandOptionId] ?? null) : null;
            $sports = [];
            foreach ((array) $bundle['slots'] as $slot) {
                $productId = (int) $slot['candidates'][0]['productId'];
                foreach ($sportOptions[$productId] ?? [] as $optionId) {
                    if (isset($sportLabels[$optionId])) {
                        $sports[$this->slugger->slug($sportLabels[$optionId])] = $sportLabels[$optionId];
                    }
                }
            }
            $meta[(int) $bundle['ruleId']] = [
                'brand' => $brand,
                'brandSlug' => $brand !== null ? $this->slugger->slug($brand) : '',
                'sports' => $sports,
                'deliveryText' => $deliveryTexts[$entryId] ?? '',
                'deliveryDirect' => $deliveryDirect[$entryId] ?? false,
            ];
        }

        return $meta;
    }

    /**
     * Request parameter as a string; a non scalar value, such as an array, counts as not given.
     */
    private function getScalarParam(string $name): string
    {
        $value = $this->request->getParam($name);

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Keeps only slug characters of a request value.
     */
    private function cleanSlug(string $value): string
    {
        return substr((string) preg_replace('/[^a-z0-9-]/', '', strtolower($value)), 0, 64);
    }
}
