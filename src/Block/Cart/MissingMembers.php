<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Block\Cart;

use FalconMedia\BundleDeals\Model\Storefront\BundleDataProvider;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

/**
 * Cart page block with the bundle deal products that would complete a set the cart has started.
 */
class MissingMembers extends Template
{
    /**
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $bundles = null;

    /**
     * @param array<string,mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly BundleDataProvider $dataProvider,
        private readonly CheckoutSession $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Returns the bundle deals the cart has started, with their missing slots.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBundles(): array
    {
        if ($this->bundles === null) {
            $quote = $this->checkoutSession->getQuote();
            $productIds = [];
            foreach ($quote->getAllVisibleItems() as $item) {
                $productIds[] = (int) $item->getProductId();
            }
            $this->bundles = $this->dataProvider->getMissingForCart(
                array_values(array_unique($productIds)),
                (int) $quote->getCustomerGroupId()
            );
        }

        return $this->bundles;
    }

    public function getAddUrl(): string
    {
        return $this->getUrl('bundledeals/cart/add');
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        return $this->getBundles() ? parent::_toHtml() : '';
    }
}
