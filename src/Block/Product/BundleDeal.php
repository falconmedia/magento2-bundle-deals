<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Block\Product;

use FalconMedia\BundleDeals\Model\Config;
use FalconMedia\BundleDeals\Model\Rule;
use FalconMedia\BundleDeals\Model\Storefront\BundleDataProvider;
use FalconMedia\BundleDeals\Model\Storefront\ColorGroupProvider;
use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Group;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Bundle deal block on the product page; one instance per possible position, only the configured one renders.
 */
class BundleDeal extends Template implements IdentityInterface
{
    /**
     * Position value for themes that render the block at a fixed place, whatever the configured position.
     */
    public const ANY_POSITION = '*';

    /**
     * Bundle data of the current product, false until loaded.
     *
     * @var array<string,mixed>|null|false
     */
    // phpcs:ignore Magento2.Commenting.ClassPropertyPHPDocFormatting.Missing
    private array|null|false $bundle = false;

    /**
     * @var ColorGroupProvider
     */
    private $colorGroupProvider;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly BundleDataProvider $dataProvider,
        private readonly Registry $registry,
        private readonly HttpContext $httpContext,
        ColorGroupProvider $colorGroupProvider,
        array $data = []
    ) {
        $this->colorGroupProvider = $colorGroupProvider;
        parent::__construct($context, $data);
    }

    /**
     * Returns the bundle deal for the current product, if any.
     *
     * @return array<string,mixed>|null
     */
    public function getBundle(): ?array
    {
        if ($this->bundle === false) {
            $product = $this->registry->registry('current_product');
            $this->bundle = $product instanceof Product
                ? $this->dataProvider->getForProduct($product, $this->getCustomerGroupId())
                : null;
        }

        return $this->bundle;
    }

    /**
     * Colour variants of the bundle deal's colour group; each URL opens the bundle deal on the entry product page.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getColorGroupVariants(int $ruleId): array
    {
        $variants = $this->colorGroupProvider->getVariantsForRule($ruleId, $this->getCustomerGroupId());
        foreach ($variants as $index => $variant) {
            if (!empty($variant['url'])) {
                $variants[$index]['url'] = strtok((string) $variant['url'], '#') . '#' . $this->getAnchorId();
            }
        }

        return $variants;
    }

    /**
     * Returns the id of the bundle deal section, used as URL fragment.
     */
    public function getAnchorId(): string
    {
        return $this->config->getAnchorId((int) $this->_storeManager->getStore()->getId());
    }

    public function getAddUrl(): string
    {
        return $this->getUrl('bundledeals/cart/add');
    }

    /**
     * Returns the order amount from which shipping is free, when the theme supplied a provider.
     */
    public function getFreeShippingThreshold(): ?float
    {
        $provider = $this->getData('free_shipping_threshold');
        if (!is_object($provider) || !method_exists($provider, 'getDisplayThreshold')) {
            return null;
        }
        $threshold = $provider->getDisplayThreshold();

        return $threshold === null ? null : (float) $threshold;
    }

    /**
     * Returns the cache tags of the rendered bundle deal.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        if (!$this->isActivePosition()) {
            return [];
        }
        $this->getBundle();

        return array_merge([Rule::CACHE_TAG], $this->dataProvider->getIdentities());
    }

    /**
     * Tells whether this block instance sits at the configured position on the product page, or ignores it.
     */
    private function isActivePosition(): bool
    {
        $position = (string) $this->getData('position');

        return $position === self::ANY_POSITION || $position === $this->config->getPosition();
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        if (!$this->isActivePosition() || !$this->getBundle()) {
            return '';
        }

        return parent::_toHtml();
    }

    private function getCustomerGroupId(): int
    {
        $groupId = $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);

        return $groupId === null ? Group::NOT_LOGGED_IN_ID : (int) $groupId;
    }
}
