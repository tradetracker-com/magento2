<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Controller\Redirect;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use TradeTracker\Connect\Api\Config\System\DirectLinkingInterface as DirectLinkingConfigRepository;

/**
 * Class Index
 * Set Tracking Cookies and redirect to TradeTracker
 */
class Index extends Action
{

    public const TRACKBACK_URL = 'https://tc.tradetracker.net/?c=%s&m=%s&a=%s&r=%s&u=%s';

    /**
     * @var Http
     */
    private $request;
    /**
     * @var Encryptor
     */
    private $encryptor;
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;
    /**
     * @var TimezoneInterface
     */
    private $date;
    /**
     * @var DirectLinkingConfigRepository
     */
    private $directLinkingConfig;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Http $request
     * @param Encryptor $encryptor
     * @param StoreManagerInterface $storeManager
     * @param TimezoneInterface $date
     * @param DirectLinkingConfigRepository $directLinkingConfig
     */
    public function __construct(
        Context $context,
        Http $request,
        Encryptor $encryptor,
        StoreManagerInterface $storeManager,
        TimezoneInterface $date,
        DirectLinkingConfigRepository $directLinkingConfig
    ) {
        parent::__construct($context);
        $this->request = $request;
        $this->encryptor = $encryptor;
        $this->storeManager = $storeManager;
        $this->date = $date;
        $this->directLinkingConfig = $directLinkingConfig;
    }

    /**
     * Set Tracking Cookies and redirect to TradeTracker
     */
    public function execute()
    {
        $trackingGroupID = '';
        $response = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        if (!$this->request->getParam('tt') || !$this->directLinkingConfig->isEnabled()) {
            return $response->setPath('/');
        }

        $domainName = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK);

        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        $domainName = empty(parse_url($domainName)['host']) ? null : '.' . parse_url($domainName)['host'];
        $trackingParam = explode('_', $this->request->getParam('tt'));
        $campaignID = $trackingParam[0] ?? '';
        $materialID = $trackingParam[1] ?? '';
        $affiliateID = $trackingParam[2] ?? '';
        $reference = $trackingParam[3] ?? '';
        $redirectURL = $this->request->getParam('r');
        $time = $this->date->scopeTimeStamp();
        $expireTime = time() + 31536000;

        // Calculate MD5 checksum.
        $checkSum = $this->encryptor->encrypt(
            implode('::', ['CHK_', $campaignID, $materialID, $affiliateID, $reference])
        );

        // Set tracking data.
        $trackingData = $materialID . '::' . $affiliateID . '::' . $reference . '::' . $checkSum . '::' . $time;

        // Set regular tracking cookie.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        setcookie('TT2_' . $campaignID, $trackingData, $expireTime, '/', $domainName);

        // Set session tracking cookie.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        setcookie('TTS_' . $campaignID, $trackingData, 0, '/', $domainName);

        // Set tracking group cookie.
        if (!empty($trackingGroupID)) {
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            setcookie('__tgdat' . $trackingGroupID, $trackingData . '_' . $campaignID, $expireTime, '/', $domainName);
        }

        // Set track-back URL.
        $trackBackURL = sprintf(
            self::TRACKBACK_URL,
            $campaignID,
            $materialID,
            $affiliateID,
            urlencode($reference),
            urlencode($redirectURL)
        );

        // Redirect to TradeTracker.
        $response->setHeader('P3P', 'CP="IDC DSP COR ADM DEVi TAIi PSA PSD IVAi IVDi CONi HIS OUR IND CNT"', true);
        $response->setHttpResponseCode(301);
        $response->setPath($trackBackURL);
        return $response;
    }
}
