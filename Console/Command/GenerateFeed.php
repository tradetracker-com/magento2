<?php
/**
 * Copyright © TradeTracker. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace TradeTracker\Connect\Console\Command;

use Magento\Framework\App\State as AppState;
use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TradeTracker\Connect\Api\Config\System\FeedInterface as FeedConfigRepository;
use TradeTracker\Connect\Api\Feed\RepositoryInterface as FeedRepository;
use TradeTracker\Connect\Console\CommandOptions\CreateFeedOptions;

/**
 * Command to create feed
 */
class GenerateFeed extends Command
{

    /**
     * Create feed command
     */
    const COMMAND_NAME = 'tradetracker:feed:create';

    /**
     * @var FeedRepository
     */
    private $feedRepository;
    /**
     * @var AppState
     */
    private $appState;
    /**
     * @var CreateFeedOptions
     */
    private $options;
    /**
     * @var FeedConfigRepository
     */
    private $feedConfigRepository;

    /**
     * CreateFeed constructor.
     * @param CreateFeedOptions $options
     * @param FeedRepository $feedRepository
     * @param FeedConfigRepository $feedConfigRepository
     * @param AppState $appState
     */
    public function __construct(
        CreateFeedOptions $options,
        FeedRepository $feedRepository,
        FeedConfigRepository $feedConfigRepository,
        AppState $appState
    ) {
        $this->options = $options;
        $this->feedRepository = $feedRepository;
        $this->feedConfigRepository = $feedConfigRepository;
        $this->appState = $appState;
        parent::__construct();
    }

    /**
     *  {@inheritdoc}
     */
    public function configure()
    {
        $this->setName(self::COMMAND_NAME);
        $this->setDescription('Generate Product Feed');
        $this->setDefinition($this->options->getOptionsList());
        parent::configure();
    }

    /**
     *  {@inheritdoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('store-id') === null) {
            $storeIds = $this->feedConfigRepository->getAllEnabledStoreIds();
        } else {
            $storeIds[] = $input->getOption('store-id');
        }

        foreach ($storeIds as $storeId) {
            $result = $this->feedRepository->generateAndSaveFeed(
                (int)$storeId,
                'CLI'
            );

            if ($result['success']) {
                $output->writeln(sprintf('<info>%s</info>', $result['message']));
            } else {
                $output->writeln(sprintf('<error>%s</error>', $result['message']));
            }
        }

        return Cli::RETURN_SUCCESS;
    }
}
