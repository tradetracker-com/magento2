<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Webapi;

use Magento\Framework\Exception\NoSuchEntityException;
use TradeTracker\Connect\Api\Webapi\ManagementInterface;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;
use TradeTracker\Connect\Api\Config\System\PixelInterface;
use TradeTracker\Connect\Api\Config\System\DirectLinkingInterface;
use TradeTracker\Connect\Api\Config\System\FeedInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Repository
 */
class Repository implements ManagementInterface
{
    /**
     * @var ConfigRepository
     */
    private $configRepository;
    /**
     * @var PixelInterface
     */
    private $pixelInterface;
    /**
     * @var DirectLinkingInterface
     */
    private $directLinkInterface;
    /**
     * @var FeedInterface
     */
    private $feedInterface;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * Repository constructor.
     *
     * @param ConfigRepository $configRepository
     * @param PixelInterface $pixelInterface
     * @param DirectLinkingInterface $directLinkInterface
     * @param FeedInterface $feedInterface
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ConfigRepository $configRepository,
        PixelInterface $pixelInterface,
        DirectLinkingInterface $directLinkInterface,
        FeedInterface $feedInterface,
        StoreManagerInterface $storeManager
    ) {
        $this->configRepository = $configRepository;
        $this->pixelInterface = $pixelInterface;
        $this->directLinkInterface = $directLinkInterface;
        $this->feedInterface = $feedInterface;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function getModuleSettings(int $storeId): array
    {
        try {
            $storeUrl = $this->storeManager->getStore($storeId)->getBaseUrl();
        } catch (NoSuchEntityException $e) {
            return ['Requested Store does not exists'];
        }
        $apiCredentials = $this->configRepository->getApiCredentials($storeId);

        return [
            [
                'enabled' => $this->configRepository->isEnabled($storeId),
                'module_version' => str_replace('v', '', $this->configRepository->getExtensionVersion()),
                'magento_version' => $this->configRepository->getMagentoVersion(),
                'customer_id' => $apiCredentials['customer_id'] ?? '',
                'passphrase' => $apiCredentials['passphrase'] ?? '',
                'campaign_id' => $this->pixelInterface->getCampaignId($storeId),
                'direct_link_url' => $storeUrl . $this->directLinkInterface->getRedirectUrl($storeId),
                'feed_url' => $storeUrl . $this->feedInterface->getFileName($storeId)
            ]
        ];
    }
}
