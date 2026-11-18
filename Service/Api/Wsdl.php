<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Api;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Stdlib\DateTime\DateTime;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;
use TradeTracker\Connect\Exception\ApiException;
use TradeTracker\Connect\Exception\ApiUnavailableException;

/**
 * A local copy of the merchant WSDL, refreshed once a day through the Transport.
 *
 * SoapClient fetches a remote WSDL over its own socket, outside any timeout this module sets, and
 * PHP's WSDL cache is per process and off on some hosts. With a file in var/ the SOAP client never
 * opens a connection for the WSDL, and a TradeTracker outage during the refresh keeps the copy it
 * has.
 */
class Wsdl
{
    public const URL = 'http://ws.tradetracker.com/soap/merchant?wsdl';
    public const FILE = 'tradetracker/merchant.wsdl';
    public const TTL = 86400;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly Transport $transport,
        private readonly DateTime $dateTime,
        private readonly LogRepository $logger
    ) {
    }

    /**
     * Absolute path of the local WSDL, fetching or refreshing it first when needed.
     *
     * @throws ApiException when there is no copy and TradeTracker does not hand one out
     */
    public function path(int $timeout): string
    {
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        if (!$this->isFresh($directory)) {
            try {
                $content = $this->transport->get(self::URL, $timeout);
                if (!str_contains($content, 'definitions')) {
                    throw new ApiUnavailableException('The WSDL answer is not a WSDL document');
                }
                $directory->writeFile(self::FILE, $content);
            } catch (ApiException | FileSystemException $e) {
                if (!$directory->isFile(self::FILE)) {
                    throw new ApiUnavailableException(
                        'Could not fetch the TradeTracker WSDL: ' . $e->getMessage(),
                        0,
                        $e
                    );
                }
                $this->logger->addDebugLog('Api', 'WSDL refresh failed, using the cached copy: ' . $e->getMessage());
            }
        }

        return $directory->getAbsolutePath(self::FILE);
    }

    private function isFresh(Filesystem\Directory\WriteInterface $directory): bool
    {
        if (!$directory->isFile(self::FILE)) {
            return false;
        }
        try {
            $modified = (int)($directory->stat(self::FILE)['mtime'] ?? 0);
        } catch (FileSystemException $e) {
            return false;
        }

        return $this->dateTime->gmtTimestamp() - $modified < self::TTL;
    }
}
