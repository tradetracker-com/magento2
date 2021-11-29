<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\ViewModel\Checkout;

use Magento\Catalog\Model\CategoryRepository;
use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;
use TradeTracker\Connect\Api\Config\System\PixelInterface as PixelConfig;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;

/**
 * Pixel view model
 *
 */
class Pixel implements ArgumentInterface
{
    /**
     * @var Session
     */
    private $checkoutSession;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var LogRepository
     */
    private $logger;
    /**
     * @var CategoryRepository
     */
    private $categoryRepository;
    /**
     * @var ConfigRepository
     */
    private $configRepository;
    /**
     * @var PixelConfig
     */
    private $pixelConfig;

    /**
     * Pixel constructor.
     *
     * @param Session $checkoutSession
     * @param LogRepository $logger
     * @param CategoryRepository $categoryRepository
     * @param ConfigRepository $configRepository
     * @param StoreManagerInterface $storeManager
     * @param PixelConfig $pixelConfig
     */
    public function __construct(
        Session $checkoutSession,
        LogRepository $logger,
        CategoryRepository $categoryRepository,
        ConfigRepository $configRepository,
        StoreManagerInterface $storeManager,
        PixelConfig $pixelConfig
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
        $this->categoryRepository = $categoryRepository;
        $this->configRepository = $configRepository;
        $this->pixelConfig = $pixelConfig;
    }

    /**
     * @return array
     */
    public function getPixelData()
    {
        $pixelData = [];

        try {
            $order = $this->checkoutSession->getLastRealOrder();
            $subtotal = ($order->getGrandTotal() - $order->getTaxAmount() - $order->getShippingAmount());
            $defaultId = $this->pixelConfig->getProductId();
            $campaignId = $this->pixelConfig->getCampaingId();

            $pixelData['campaign_id'] = $campaignId;
            $pixelData['transaction_id'] = $order->getIncrementId();
            $pixelData['transactions'][$defaultId]['amount'] = number_format($subtotal, 2, '.', '');
            $pixelData['currency'] = $order->getOrderCurrencyCode();
            $pixelData['vc'] = $order->getCouponCode();

            if ($customCurrencyCode = $this->pixelConfig->getCustomCurrencyCode((int)$order->getStoreId())) {
                $pixelData['currency'] = $customCurrencyCode;
            }

            foreach ($order->getAllVisibleItems() as $item) {
                $categoryIds = $item->getProduct()->getCategoryIds();
                foreach ($categoryIds as $categoryId) {
                    $category = $this->categoryRepository->get($categoryId, $this->storeManager->getStore()->getId());
                    $ttProductId = $category->getData('tradetracker_product_id');
                    if (!empty($ttProductId) && ($ttProductId != $defaultId)) {
                        $pixelData['transactions'][$defaultId]['amount'] -= $item['base_row_total'];
                        if (!empty($pixelData['transactions'][$ttProductId]['amount'])) {
                            $pixelData['transactions'][$ttProductId]['amount'] += $item['base_row_total'];
                        } else {
                            $pixelData['transactions'][$ttProductId]['amount'] = $item['base_row_total'];
                        }
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->addDebugLog('getPixelData', $e->getMessage());
        }

        return $pixelData;
    }
}
