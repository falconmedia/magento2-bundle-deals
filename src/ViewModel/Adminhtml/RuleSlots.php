<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\ViewModel\Adminhtml;

use FalconMedia\BundleDeals\Model\ResourceModel\Index;
use FalconMedia\BundleDeals\Model\ResourceModel\Slot\CollectionFactory;
use FalconMedia\BundleDeals\Model\Slot;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\CurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Slot overview of the bundle deal on the rule edit page.
 */
class RuleSlots implements ArgumentInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CollectionFactory $collectionFactory,
        private readonly Index $index,
        private readonly UrlInterface $urlBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly CurrencyInterface $localeCurrency
    ) {
    }

    public function getRuleId(): int
    {
        return (int) $this->request->getParam('rule_id');
    }

    /**
     * Slots of the current rule with their edit URL and number of indexed products.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getSlots(): array
    {
        $ruleId = $this->getRuleId();
        if (!$ruleId) {
            return [];
        }

        $rows = [];
        /**
         * @var Slot $slot
         */
        foreach ($this->collectionFactory->create()->addRuleFilter($ruleId) as $slot) {
            $rows[] = [
                'title' => (string) $slot->getData('title'),
                'sort_order' => (int) $slot->getData('sort_order'),
                'entry' => $slot->isEntry(),
                'discount' => $this->formatDiscount($slot),
                'products' => count($this->index->getSlotProductIds($ruleId, (int) $slot->getId())),
                'edit_url' => $this->urlBuilder->getUrl('bundledeals/slot/edit', ['slot_id' => $slot->getId()]),
            ];
        }

        return $rows;
    }

    public function getAddUrl(): string
    {
        return $this->urlBuilder->getUrl('bundledeals/slot/new', ['rule_id' => $this->getRuleId()]);
    }

    private function formatDiscount(Slot $slot): string
    {
        $amount = $slot->getDiscountAmount();

        return match ($slot->getDiscountType()) {
            Slot::DISCOUNT_PERCENT => (string) __('%1%', rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.')),
            Slot::DISCOUNT_FIXED => (string) __(
                '%1 off',
                $this->localeCurrency
                    ->getCurrency($this->storeManager->getStore()->getBaseCurrencyCode())
                    ->toCurrency($amount)
            ),
            default => (string) __('None'),
        };
    }
}
