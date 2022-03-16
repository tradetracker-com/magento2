<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace TradeTracker\Connect\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use TradeTracker\Connect\Api\Log\RepositoryInterface as LogRepository;

/**
 * After save cron function
 */
class Cron extends Value
{
    /**
     * Config path of TradeTracker Cron Schedule
     */
    public const CRON_STRING_PATH = 'crontab/default/jobs/tradetracker/schedule/cron_expr';

    /**
     * @var WriterInterface
     */
    private $configWriter;
    /**
     * @var LogRepository
     */
    private $logRepository;

    /**
     * Cron constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param ScopeConfigInterface $config
     * @param TypeListInterface $cacheTypeList
     * @param WriterInterface $configWriter
     * @param LogRepository $logRepository
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        WriterInterface $configWriter,
        LogRepository $logRepository,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->configWriter = $configWriter;
        $this->logRepository = $logRepository;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Cron settings after save
     *
     * @return Value
     * @throws LocalizedException
     */
    public function afterSave()
    {
        $cronExprString = $this->getData('groups/general/fields/cron_frequency/value');

        if ($cronExprString == 'custom') {
            $cronExprString = $this->getData('groups/general/fields/custom_cron_frequency/value');
        }

        try {
            $this->configWriter->save(self::CRON_STRING_PATH, $cronExprString);
        } catch (\Exception $e) {
            $this->logRepository->addErrorLog('Save Cron Expression', $e->getMessage());
            throw new LocalizedException(__('We can\'t save the cron expression.'));
        }

        return parent::afterSave();
    }
}
