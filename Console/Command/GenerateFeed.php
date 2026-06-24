<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TradeTracker\Connect\Api\Config\RepositoryInterface as ConfigRepository;
use TradeTracker\Connect\Api\Feed\RepositoryInterface as FeedRepository;

class GenerateFeed extends Command
{
    public const COMMAND_NAME = 'tradetracker:feed:create';

    public function __construct(
        private readonly State $state,
        private readonly ConfigRepository $configRepository,
        private readonly FeedRepository $feedRepository,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Generate Product Feed');
        $this->addOption('store-id', null, InputOption::VALUE_OPTIONAL, 'Store ID');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $this->state->setAreaCode('adminhtml');
        } catch (\Throwable $e) {
            // Area code already set
        }

        if (!$this->configRepository->isEnabled()) {
            $output->writeln('<error>Module is not enabled.</error>');
            return Cli::RETURN_FAILURE;
        }

        try {
            $storeIds = $input->getOption('store-id') !== null
                ? [(int)$input->getOption('store-id')]
                : [];

            $this->feedRepository->cliProcess($output, $storeIds);
            return Cli::RETURN_SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>%s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
    }
}
