<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Observer\Config;

use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Api\StoreRepositoryInterface as StoreRepository;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;
use TradeTracker\Connect\Service\DirectLinking\UrlRewrite;

/**
 * Class SaveRewrite - Saves Url Rewrite for DirectLinking
 * Event: admin_system_config_changed_section_magmodules_tradetracker
 */
class SaveRewrite implements ObserverInterface
{

    /**
     * @var RequestInterface
     */
    private $request;
    /**
     * @var WriterInterface
     */
    private $configWriter;
    /**
     * @var UrlRewrite
     */
    private $urlRewrite;
    /**
     * @var ConfigRepository
     */
    private $configRepository;
    /**
     * @var StoreRepository
     */
    private $storeRepository;

    /**
     * SaveRewrite constructor.
     * @param RequestInterface $request
     * @param WriterInterface $configWriter
     * @param UrlRewrite $urlRewrite
     * @param ConfigRepository $configRepository
     * @param StoreRepository $storeRepository
     */
    public function __construct(
        RequestInterface $request,
        WriterInterface $configWriter,
        UrlRewrite $urlRewrite,
        ConfigRepository $configRepository,
        StoreRepository $storeRepository
    ) {
        $this->request = $request;
        $this->configWriter = $configWriter;
        $this->urlRewrite = $urlRewrite;
        $this->configRepository = $configRepository;
        $this->storeRepository = $storeRepository;
    }

    /**
     * @param EventObserver $observer
     * @return $this
     */
    public function execute(EventObserver $observer): self
    {
        $meetParams = $this->request->getParam('groups');
        $storeId = $this->request->getParam('store');

        if (!isset($meetParams['redirect']['fields']['url_key'])) {
            return $this;
        }

        if (!array_key_exists('value', $meetParams['redirect']['fields']['url_key'])) {
            $data = [
                'entity_type' => 'trade_tracker',
                'entity_id' => 0,
                'request_path' => $this->configRepository->getRedirectUrl(),
                'target_path' => 'tradetracker/redirect',
                'store_id' => $storeId,
                'description' => 'TradeTracker redirect URL'
            ];
            $this->urlRewrite->execute($data);
            return $this;
        }
        $requestPath = $meetParams['redirect']['fields']['url_key']['value'];
        if ($storeId && $requestPath) {
            $data = [
                'entity_type' => 'trade_tracker',
                'entity_id' => 0,
                'request_path' => $requestPath,
                'target_path' => 'tradetracker/redirect',
                'store_id' => $storeId,
                'description' => 'TradeTracker redirect URL'
            ];
            $this->urlRewrite->execute($data);
        } elseif ($requestPath) {
            foreach ($this->storeRepository->getList() as $store) {
                $data = [
                    'entity_type' => 'trade_tracker',
                    'entity_id' => 0,
                    'request_path' => $this->configRepository->getRedirectUrl((int)$store->getId()),
                    'target_path' => 'tradetracker/redirect',
                    'store_id' => $store->getId(),
                    'description' => 'TradeTracker redirect URL'
                ];
                $this->urlRewrite->execute($data);
            }
        }
        return $this;
    }
}
