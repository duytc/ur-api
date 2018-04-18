<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\StringUtilTrait;

class ReloadAllImportedDataCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:imported-data:reload-all')
            ->addOption('all-publishers', 'a', InputOption::VALUE_NONE,
                'Enable for all users')
            ->addOption('specify-publishers', 'u', InputOption::VALUE_OPTIONAL,
                'Enable for special users, allow multiple userId separated by comma, e.g. -u "5,10,3"')
            ->setDescription('reload all imported data for all data set');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->notice('starting command...');

        $userIdsStringOption = $input->getOption('specify-publishers');
        $isAllPublisherOption = $input->getOption('all-publishers');

        if (!$this->validateInput($input, $output)) {
            return 0;
        }

        /** @var ImportHistoryManagerInterface $importHistoryManager */
        $dataSetManager = $container->get('ur.domain_manager.data_set');

        $dataSets = [];
        if ($isAllPublisherOption) {
            $dataSets = $dataSetManager->all();
        } else {
            /** @var PublisherManagerInterface $publisherManager */
            $publisherManager = $container->get('ur_user.domain_manager.publisher');
            $updatePublisherIds = null;
            $userIdsStringOption = explode(',', $userIdsStringOption);

            $updatePublisherIds = array_map(function ($userId) {
                return trim($userId);
            }, $userIdsStringOption);

            foreach ($updatePublisherIds as $publisherId) {
                $publisher = $publisherManager->find($publisherId);
                if ($publisher === null) {
                    $this->logger->error(sprintf('warning: publisher with ID %s not found', $publisherId));
                    continue;
                }

                try {
                    $dataSetByPublishers = $dataSetManager->getDataSetForPublisher($publisher);
                } catch (\Exception $exception) {
                    $this->logger->error(sprintf('error occur: %s', $exception->getMessage()));
                    continue;
                }

                $dataSets = array_merge($dataSets, $dataSetByPublishers);
            }
        }

        $workerManager = $container->get('ur.worker.manager');
        /** @var DataSetInterface[] $dataSets */
        $this->logger->notice(sprintf('reloading %s data sets', count($dataSets)));
        $numOfDataSetReloaded = 0;
        foreach ($dataSets as $dataSet) {
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }
            $workerManager->reloadAllForDataSet($dataSet);
            $numOfDataSetReloaded++;
        }

        $this->logger->notice(sprintf('%s data sets are reloading', $numOfDataSetReloaded));
        $this->logger->notice(sprintf('command run successfully'));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput(InputInterface $input, OutputInterface $output)
    {
        // validate specify-publishers and all publishers
        $hasUserIdsStringOption = $input->getOption('specify-publishers') !== null;
        $isAllPublisherOption = $input->getOption('all-publishers');
        if ((!$hasUserIdsStringOption && !$isAllPublisherOption) || ($hasUserIdsStringOption && $isAllPublisherOption)) {
            $output->writeln(sprintf('command run failed: invalid publishers info, require only one of options -u or -a'));
            return false;
        }

        // validate specify-publishers
        if ($hasUserIdsStringOption) {
            $userIdsStringOption = $input->getOption('specify-publishers');

            if (empty($userIdsStringOption)) {
                $output->writeln(sprintf('command run failed: specify-publishers must not be null or empty'));
                return false;
            }
        }

        return true;
    }
}