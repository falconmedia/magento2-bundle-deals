<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Block\Widget;

use FalconMedia\BundleDeals\Model\Rule;
use FalconMedia\BundleDeals\Model\Storefront\BundleDataProvider;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Customer\Model\Group;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;

/**
 * CMS widget listing the active bundle deals with their default products.
 */
class BundleOverview extends Template implements BlockInterface, IdentityInterface
{
    /**
     * @var string
     */
    protected $_template = 'FalconMedia_BundleDeals::widget/bundle-overview.phtml';

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
        private readonly HttpContext $httpContext,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Returns the bundle deals shown by the widget.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBundles(): array
    {
        if ($this->bundles === null) {
            $groupId = $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP);
            $this->bundles = $this->dataProvider->getOverview(
                $groupId === null ? Group::NOT_LOGGED_IN_ID : (int) $groupId,
                max(1, (int) ($this->getData('limit') ?: 10))
            );
        }

        return $this->bundles;
    }

    public function getTitle(): string
    {
        return (string) $this->getData('title');
    }

    /**
     * Heading level of the widget title: 1 when the widget is the main content of the page, else 2.
     */
    public function getHeadingLevel(): int
    {
        return (int) $this->getData('heading_level') === 1 ? 1 : 2;
    }

    /**
     * Title of a bundle card, empty when it only repeats the widget title.
     *
     * @param array<string,mixed> $bundle
     */
    public function getBundleTitle(array $bundle): string
    {
        $title = trim((string) ($bundle['title'] ?? ''));

        return mb_strtolower($title) === mb_strtolower(trim($this->getTitle())) ? '' : $title;
    }

    /**
     * Set price of the default products: the sum without and with the slot discounts.
     *
     * @param array<string,mixed> $bundle
     * @return array{regular: float, total: float}
     */
    public function getTotals(array $bundle): array
    {
        $regular = 0.0;
        $total = 0.0;
        foreach ($bundle['slots'] as $slot) {
            $price = (float) $slot['candidates'][0]['finalPrice'];
            $regular += $price;
            $amount = (float) $slot['discount']['amount'];
            $onSpecialPrice = !empty($slot['discount']['excludeSpecialPrice'])
                && $price < (float) $slot['candidates'][0]['regularPrice'];
            $total += $onSpecialPrice ? $price : match ($slot['discount']['type']) {
                'percent' => $price * (1 - min($amount, 100) / 100),
                'fixed' => max(0.0, $price - $amount),
                default => $price,
            };
        }

        return ['regular' => round($regular, 2), 'total' => round($total, 2)];
    }

    public function formatPrice(float $amount): string
    {
        return $this->priceCurrency->format($amount, false);
    }

    /**
     * Returns the cache tags of the rendered bundle deals.
     *
     * @return string[]
     */
    public function getIdentities(): array
    {
        $this->getBundles();

        return array_merge([Rule::CACHE_TAG], $this->dataProvider->getIdentities());
    }
}
