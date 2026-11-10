<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Controller\Redirect;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Laminas\Uri\UriFactory;
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
    private CookieManagerInterface $cookieManager;
    private CookieMetadataFactory $cookieMetadataFactory;

    /**
     * Index constructor.
     *
     * @param Context $context
     * @param Http $request
     * @param Encryptor $encryptor
     * @param StoreManagerInterface $storeManager
     * @param TimezoneInterface $date
     * @param DirectLinkingConfigRepository $directLinkingConfig
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     */
    public function __construct(
        Context $context,
        Http $request,
        Encryptor $encryptor,
        StoreManagerInterface $storeManager,
        TimezoneInterface $date,
        DirectLinkingConfigRepository $directLinkingConfig,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        parent::__construct($context);
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
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
        $response = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        if (!$this->request->getParam('tt') || !$this->directLinkingConfig->isEnabled()) {
            return $response->setPath('/');
        }

        $host = (string)UriFactory::factory(
            $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK)
        )->getHost();
        $domainName = $host === '' ? '' : '.' . $host;
        $trackingParam = explode('_', (string)$this->request->getParam('tt'));
        $campaignID = $trackingParam[0] ?? '';
        $materialID = $trackingParam[1] ?? '';
        $affiliateID = $trackingParam[2] ?? '';
        $reference = $trackingParam[3] ?? '';
        $redirectURL = $this->request->getParam('r');
        $time = $this->date->scopeTimeStamp();
        $yearInSeconds = 31536000;

        // Calculate MD5 checksum.
        $checkSum = $this->encryptor->encrypt(
            implode('::', ['CHK_', $campaignID, $materialID, $affiliateID, $reference])
        );

        // Set tracking data.
        $trackingData = $materialID . '::' . $affiliateID . '::' . $reference . '::' . $checkSum . '::' . $time;

        // Set regular tracking cookie (a year) and session tracking cookie (until the browser closes).
        $this->setTrackingCookie('TT2_' . $campaignID, $trackingData, $domainName, $yearInSeconds);
        $this->setTrackingCookie('TTS_' . $campaignID, $trackingData, $domainName, null);

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

    /**
     * A tracking cookie the way the pixel reads it: on the shop's domain, for the whole path, readable by
     * script, and a session cookie when no duration is given.
     */
    private function setTrackingCookie(string $name, string $value, string $domain, ?int $duration): void
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setPath('/')
            ->setHttpOnly(false)
            ->setSecure(false);
        if ($domain !== '') {
            $metadata->setDomain($domain);
        }
        if ($duration !== null) {
            $metadata->setDuration($duration);
        }

        $this->cookieManager->setPublicCookie($name, $value, $metadata);
    }
}
