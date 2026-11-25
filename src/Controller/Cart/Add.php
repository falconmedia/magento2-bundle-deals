<?php

/**
 * @package     FalconMedia_BundleDeals
 * @author      Henk Valk (henk@falconmedia.nl)
 *
 * Copyright (c) Falcon Media (www.falconmedia.nl)
 */

declare(strict_types=1);

namespace FalconMedia\BundleDeals\Controller\Cart;

use FalconMedia\BundleDeals\Model\ResourceModel\Index;
use FalconMedia\BundleDeals\Model\Set\ActiveRuleProvider;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Adds the products of a bundle deal to the cart in one request; nothing is added when one product fails.
 *
 * With complete=1 (default) every slot must be filled once; with complete=0 any members may be added,
 * which the cart page uses to complete a started set.
 */
class Add implements HttpPostActionInterface
{
    private const MAX_QTY = 10;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ActiveRuleProvider $activeRuleProvider,
        private readonly Index $index,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly EventManager $eventManager,
        private readonly ResponseInterface $response
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $storeId = (int) $this->storeManager->getStore()->getId();
        $ruleId = (int) $this->request->getParam('rule_id');
        $complete = (string) $this->request->getParam('complete', '1') !== '0';
        $items = $this->request->getParam('items');
        $qty = max(1, min(self::MAX_QTY, (int) $this->request->getParam('qty', 1)));

        if (!$this->activeRuleProvider->getRule($ruleId, $storeId) || !is_array($items) || !$items) {
            return $result->setData([
                'success' => false,
                'message' => (string) __('This bundle deal is no longer available.'),
                'errors' => [],
            ]);
        }

        $slotIds = $this->index->getSlotIdsByRule([$ruleId])[$ruleId] ?? [];
        if (count($items) > count($slotIds)) {
            return $result->setData([
                'success' => false,
                'message' => (string) __('This product is not part of the bundle deal.'),
                'errors' => [],
            ]);
        }
        $errors = [];
        $requests = [];
        foreach ($items as $item) {
            $slotId = (int) ($item['slot_id'] ?? 0);
            $productId = (int) ($item['product_id'] ?? 0);
            if (!in_array($slotId, $slotIds, true) || isset($requests[$slotId])
                || !$this->index->isProductInSlot($ruleId, $slotId, $productId)
            ) {
                $errors[] = [
                    'slotId' => $slotId,
                    'message' => (string) __('This product is not part of the bundle deal.'),
                ];
                continue;
            }
            $superAttribute = array_filter(
                array_map('intval', (array) ($item['super_attribute'] ?? [])),
                static fn (int $value): bool => $value > 0
            );
            $requests[$slotId] = ['product_id' => $productId, 'super_attribute' => $superAttribute];
        }
        $requiredSlotIds = array_diff($slotIds, $this->index->getOptionalSlotIds([$ruleId]));
        if ($complete && !$errors && array_diff($requiredSlotIds, array_keys($requests))) {
            $errors[] = [
                'slotId' => 0,
                'message' => (string) __('Choose a product for every part of the bundle deal.'),
            ];
        }
        if ($errors) {
            return $result->setData(['success' => false, 'message' => $errors[0]['message'], 'errors' => $errors]);
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            $websiteId = (int) $this->storeManager->getStore()->getWebsiteId();
            $addedProducts = [];
            foreach ($requests as $slotId => $data) {
                $product = $this->productRepository->getById($data['product_id'], false, $storeId, true);
                if (!$product instanceof Product
                    || $product->isDisabled()
                    || !$product->isVisibleInSiteVisibility()
                    || !in_array($websiteId, array_map('intval', (array) $product->getWebsiteIds()), true)
                ) {
                    return $result->setData([
                        'success' => false,
                        'message' => (string) __('This product is no longer available.'),
                        'errors' => [],
                    ]);
                }
                $buyRequest = new DataObject(['qty' => $qty, 'product' => $data['product_id']]);
                if ($data['super_attribute']) {
                    $buyRequest->setData('super_attribute', $data['super_attribute']);
                }
                $added = $quote->addProduct($product, $buyRequest);
                if (is_string($added)) {
                    $errors[] = ['slotId' => $slotId, 'message' => $added];
                    continue;
                }
                $addedProducts[] = $product;
            }
            if ($errors) {
                return $result->setData(['success' => false, 'message' => $errors[0]['message'], 'errors' => $errors]);
            }
            // Totals are collected per address, so a new quote needs its addresses first (as Checkout Cart::save does).
            $quote->getBillingAddress();
            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->collectTotals();
            $this->cartRepository->save($quote);
            $this->checkoutSession->setQuoteId($quote->getId());
            foreach ($addedProducts as $product) {
                $this->eventManager->dispatch(
                    'checkout_cart_add_product_complete',
                    ['product' => $product, 'request' => $this->request, 'response' => $this->response]
                );
            }
        } catch (NoSuchEntityException) {
            return $result->setData([
                'success' => false,
                'message' => (string) __('This product is no longer available.'),
                'errors' => [],
            ]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage(), 'errors' => []]);
        } catch (\Throwable $e) {
            $this->logger->error('Bundle deal add to cart failed: ' . $e->getMessage(), ['exception' => $e]);
            return $result->setData([
                'success' => false,
                'message' => (string) __('We can\'t add this bundle deal to your shopping cart right now.'),
                'errors' => [],
            ]);
        }

        return $result->setData([
            'success' => true,
            'message' => (string) __('The bundle deal has been added to your shopping cart.'),
            'errors' => [],
        ]);
    }
}
