<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Service\Feed;

use Magento\Framework\Filesystem\Io\File;

/**
 * Feed creator service
 */
class Create
{

    /**
     * @var File
     */
    private $file;

    /**
     * Create constructor.
     * @param File $file
     */
    public function __construct(
        File $file
    ) {
        $this->file = $file;
    }

    /**
     * @param array $feed
     * @param int $storeId
     * @param string $path
     */
    public function execute(array $feed, int $storeId, string $path)
    {
        $xmlStr = <<<XML
<?xml version="1.0" encoding="utf-8"?>
XML;
        $xmlStr .= $this->createXml($feed);

        $fileInfo = $this->file->getPathInfo($path);
        $this->file->mkdir($fileInfo['dirname']);
        $this->file->write($path, $xmlStr);
    }

    /**
     * @param array $data
     * @return string
     */
    public function createXml($data)
    {
        $xmlStr = '';
        foreach ($data as $key => $value) {
            if (is_numeric($key)) {
                $key = 'item';
            }
            if (!is_array($value)) {
                // phpcs:ignore Magento2.Functions.DiscouragedFunction
                $value = htmlspecialchars((string)$value, ENT_XML1, 'UTF-8');
                $xmlStr .= <<<XML
<$key>$value
XML;
            } else {
                $subData = $this->createXml($value);
                $xmlStr .= <<<XML
<$key>$subData
XML;
            }
            $xmlStr .= <<<XML
</$key>

XML;
        }
        return $xmlStr;
    }
}
