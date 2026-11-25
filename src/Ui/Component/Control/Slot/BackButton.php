<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Ui\Component\Control\Slot;

use FalconMedia\BundleDeals\Model\ResourceModel\Slot as SlotResource;
use FalconMedia\BundleDeals\Model\SlotFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class BackButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $urlBuilder,
        private readonly SlotFactory $slotFactory,
        private readonly SlotResource $slotResource
    ) {
    }

    /**
     * Returns the button configuration.
     *
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $ruleId = (int) $this->request->getParam('rule_id');
        $slotId = (int) $this->request->getParam('slot_id');
        if ($slotId) {
            $slot = $this->slotFactory->create();
            $this->slotResource->load($slot, $slotId);
            $ruleId = $slot->getRuleId();
        }
        $url = $ruleId
            ? $this->urlBuilder->getUrl('bundledeals/rule/edit', ['rule_id' => $ruleId])
            : $this->urlBuilder->getUrl('bundledeals/rule/');

        return [
            'label' => __('Back to Bundle Deal'),
            'on_click' => sprintf("location.href = '%s';", $url),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
