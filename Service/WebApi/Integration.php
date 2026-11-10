<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\WebApi;

use Magento\Framework\Exception\IntegrationException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Integration\Api\AuthorizationServiceInterface;
use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Model\Oauth\Token;

/**
 * Service model to create, delete and get integration token
 */
class Integration
{

    public const INTEGRATION_NAME = 'TradeTracker Integration';

    /**
     * @var IntegrationServiceInterface
     */
    private $integrationService;
    /**
     * @var OauthServiceInterface
     */
    private $oauthService;
    /**
     * @var AuthorizationServiceInterface
     */
    private $authorizationService;

    /**
     * @param IntegrationServiceInterface $integrationService
     * @param OauthServiceInterface $oauthService
     * @param AuthorizationServiceInterface $authorizationService
     */
    public function __construct(
        IntegrationServiceInterface $integrationService,
        OauthServiceInterface $oauthService,
        AuthorizationServiceInterface $authorizationService
    ) {
        $this->integrationService = $integrationService;
        $this->oauthService = $oauthService;
        $this->authorizationService = $authorizationService;
    }

    /**
     * Create a new integration
     *
     * @param bool $update
     *
     * @return string
     * @throws IntegrationException
     * @throws LocalizedException
     */
    public function createToken(bool $update = false): string
    {
        $integration = $this->integrationService->findByName(self::INTEGRATION_NAME);
        if ($integration->getId()) {
            $customerId = $integration->getConsumerId();
            return $this->accessToken((int)$customerId);
        }

        $integrationData = [
            'name' => self::INTEGRATION_NAME,
            'endpoint' => '',
            'status' => '1',
            'setup_type' => '0',
        ];

        $integration = $this->integrationService->create($integrationData);
        $integrationId = $integration->getId();
        $customerId = $integration->getConsumerId();
        $this->authorizationService->grantPermissions($integrationId, ['TradeTracker_Connect::webapi']);

        $this->oauthService->createAccessToken($customerId, true);
        return $this->accessToken((int)$customerId);
    }

    /**
     * @throws LocalizedException when the consumer has no access token
     */
    private function accessToken(int $customerId): string
    {
        $token = $this->oauthService->getAccessToken($customerId);
        if (!$token instanceof Token) {
            throw new LocalizedException(__('The TradeTracker integration has no access token'));
        }

        return (string)$token->getToken();
    }

    /**
     * @return string|void
     */
    public function getToken()
    {
        $integration = $this->integrationService->findByName(self::INTEGRATION_NAME);
        if ($integration->getId()) {
            $customerId = $integration->getConsumerId();
            return $this->accessToken((int)$customerId);
        }
    }
}
