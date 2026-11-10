<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Observer\Config;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Api\StoreRepositoryInterface as StoreRepository;
use TradeTracker\Connect\Api\Config\System\DirectLinkingInterface as ConfigRepository;
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
     * @param UrlRewrite $urlRewrite
     * @param ConfigRepository $configRepository
     * @param StoreRepository $storeRepository
     */
    public function __construct(
        RequestInterface $request,
        UrlRewrite $urlRewrite,
        ConfigRepository $configRepository,
        StoreRepository $storeRepository
    ) {
        $this->request = $request;
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
        if (!isset($meetParams['redirect_linking']['fields']['url_key'])) {
            return $this;
        }

        $field = $meetParams['redirect_linking']['fields']['url_key'];
        $storeId = (int)$this->request->getParam('store');

        // "Use system value" posts the field without a value: the key then comes from the scope the
        // merchant is saving, which is the inherited value for that store or, on Default Config,
        // the value every store view inherits.
        $inherited = !array_key_exists('value', $field);
        $requestPath = $inherited ? null : (string)$field['value'];

        if (!$inherited && $requestPath === '') {
            return $this;
        }

        if ($storeId) {
            $this->saveRewrite($requestPath ?? $this->configRepository->getRedirectUrl($storeId), $storeId);
            return $this;
        }

        foreach ($this->storeRepository->getList() as $store) {
            $this->saveRewrite($this->configRepository->getRedirectUrl((int)$store->getId()), (int)$store->getId());
        }

        return $this;
    }

    private function saveRewrite(string $requestPath, int $storeId): void
    {
        if ($requestPath === '') {
            return;
        }

        $this->urlRewrite->execute([
            'entity_type' => 'trade_tracker',
            'entity_id' => 0,
            'request_path' => $requestPath,
            'target_path' => 'tradetracker/redirect',
            'store_id' => $storeId,
            'description' => 'TradeTracker redirect URL'
        ]);
    }
}
