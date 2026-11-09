<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use TradeTracker\Connect\Model\Config\System\Source\CronFrequency;

/**
 * Resolves the Frequency field to the cron expression cron itself reads.
 *
 * etc/crontab.xml points <config_path> straight at this field, so the value stored here is the
 * schedule — there is no second row to keep in step. That matters beyond tidiness: anything that
 * writes configuration without a backend model (Framework\App\Config\Storage\Writer::save(), used
 * by data patches, plus direct SQL and database restores between environments) would leave a copy
 * stale forever, showing one frequency in the admin while cron ran another, with nothing logged.
 *
 * The Frequency select still offers "Custom" with a free-text companion field, which is why this
 * model exists at all:
 *
 *   beforeSave() turns the posted "custom" into the expression from Custom Frequency
 *   afterLoad()  turns a stored expression that is not a preset back into "custom" for display
 *
 * Both halves are needed: without afterLoad() the select would fall back to its first option for a
 * merchant on a custom expression, and the next save would silently reschedule their feed.
 */
class Cron extends Value
{

    public const CUSTOM = 'custom';

    public const FREQUENCY_PATH = 'tradetracker/feed/cron_frequency';
    public const CUSTOM_FREQUENCY_PATH = 'tradetracker/feed/custom_cron_frequency';

    private const CUSTOM_FREQUENCY_FIELD = 'custom_cron_frequency';

    private CronFrequency $cronFrequency;

    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        CronFrequency $cronFrequency,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->cronFrequency = $cronFrequency;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Store an expression, never the word "custom".
     *
     * @return Value
     */
    public function beforeSave()
    {
        $value = $this->clean($this->getValue());

        if ($value === self::CUSTOM) {
            $value = $this->customExpression() ?? $this->storedExpression();
        }

        // Nothing usable was posted: keep whatever is scheduled now rather than clearing it. An
        // empty value here would leave the job with no frequency at all.
        $this->setValue($value ?? $this->storedExpression() ?? '');

        return parent::beforeSave();
    }

    /**
     * Show "Custom" for an expression the select does not offer as a preset.
     *
     * @return Value
     */
    public function afterLoad()
    {
        $value = $this->clean($this->getValue());

        if ($value !== null && !in_array($value, $this->presets(), true)) {
            $this->setValue(self::CUSTOM);
        }

        return parent::afterLoad();
    }

    /**
     * The Custom Frequency companion field: posted alongside the form, otherwise read back from
     * stored configuration so a save that carries no form data still resolves.
     */
    private function customExpression(): ?string
    {
        $posted = $this->getData(sprintf('groups/general/fields/%s', self::CUSTOM_FREQUENCY_FIELD));

        // Posted at all is the distinction that matters, not posted non-empty. A merchant who
        // clears the field has said something, and falling back to stored configuration there would
        // quietly reschedule them onto the config.xml default instead of leaving them be.
        if (is_array($posted) && array_key_exists('value', $posted)) {
            return $this->clean($posted['value']);
        }

        return $this->clean($this->config(self::CUSTOM_FREQUENCY_PATH));
    }

    /**
     * The expression currently scheduled, ignoring a stored "custom" left behind by an older
     * version of this module.
     */
    private function storedExpression(): ?string
    {
        $stored = $this->clean($this->config(self::FREQUENCY_PATH));

        return $stored === self::CUSTOM ? null : $stored;
    }

    /**
     * @return string[]
     */
    private function presets(): array
    {
        $presets = [];

        foreach ($this->cronFrequency->toOptionArray() as $option) {
            $value = (string)($option['value'] ?? '');

            if ($value !== '' && $value !== self::CUSTOM) {
                $presets[] = $value;
            }
        }

        return $presets;
    }

    /**
     * @return mixed
     */
    private function config(string $path)
    {
        return $this->_config->getValue(
            $path,
            $this->getScope() ?: ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            $this->getScopeId()
        );
    }

    /**
     * @param mixed $value
     */
    private function clean($value): ?string
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }
}
