<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Log;

use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepositoryInterface;
use Magento\Framework\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Stdlib\DateTime\DateTime;
use TradeTracker\Connect\Logger\DebugLogger;
use TradeTracker\Connect\Logger\ErrorLogger;

/**
 * Logs repository class
 */
class Repository implements LogRepositoryInterface
{

    private DebugLogger $debugLogger;
    private ErrorLogger $errorLogger;
    private DirectoryList $dir;
    private DateTime $dateTime;
    private File $file;

    /**
     * Repository constructor.
     *
     * @param DebugLogger $debugLogger
     * @param ErrorLogger $errorLogger
     * @param DirectoryList $dir
     * @param File $file
     * @param DateTime $dateTime
     */
    public function __construct(
        DebugLogger $debugLogger,
        ErrorLogger $errorLogger,
        DirectoryList $dir,
        File $file,
        DateTime $dateTime
    ) {
        $this->debugLogger = $debugLogger;
        $this->errorLogger = $errorLogger;
        $this->dir = $dir;
        $this->file = $file;
        $this->dateTime = $dateTime;
    }

    /**
     * @inheritDoc
     */
    public function addErrorLog(string $type, $data): void
    {
        $this->errorLogger->addLog($type, $data);
    }

    /**
     * @inheritDoc
     */
    public function addDebugLog(string $type, $data): void
    {
        $this->debugLogger->addLog($type, $data);
    }

    /**
     * @inheritDoc
     */
    public function getLogFilePath(string $type): ?string
    {
        try {
            return sprintf(LogRepositoryInterface::LOG_FILE, $this->dir->getPath('var'), $type);
        } catch (\Exception $exception) {
            return null;
        }
    }

    /**
     * @inheritDoc
     */
    public function getLogEntriesAsArray(string $path, ?int $limit = null): ?array
    {
        try {
            if (!$this->file->isExists($path)) {
                return null;
            }

            $stream = $this->file->fileOpen($path, 'r');
            $this->file->fileSeek($stream, 0, SEEK_END);
            $pos = $this->file->fileTell($stream);
            $numberOfLines = LogRepository::STREAM_DEFAULT_LIMIT;
            while ($pos >= 0 && $numberOfLines > 0) {
                $this->file->fileSeek($stream, $pos);
                // phpcs:ignore Magento2.Functions.DiscouragedFunction
                $char = fgetc($stream);
                if ($char === "\n") {
                    $numberOfLines--;
                }
                $pos--;
            }

            $result = [];
            // phpcs:ignore Magento2.Functions.DiscouragedFunction
            while (!feof($stream) && $numberOfLines < LogRepository::STREAM_DEFAULT_LIMIT) {
                // phpcs:ignore Magento2.Functions.DiscouragedFunction
                if ($line = fgets($stream)) {
                    $data = explode('] ', $line);
                    $date = ltrim(array_shift($data), '[');
                    $data = implode('] ', $data);
                    $data = explode(': ', $data);
                    unset($data[0]);
                    $type = $data[1] ?? '--';
                    array_shift($data);

                    $result[] = [
                        'date' => $this->dateTime->date('Y-m-d H:i:s', $date) . ' - ' . $type,
                        'msg' => implode(': ', $data)
                    ];
                }
            }

            $this->file->fileClose($stream);
            return array_reverse($result);
        } catch (\Exception $exception) {
            return null;
        }
    }
}
