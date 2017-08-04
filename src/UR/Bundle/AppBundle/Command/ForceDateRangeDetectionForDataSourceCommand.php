<?php

namespace UR\Bundle\AppBundle\Command;

use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;

class ForceDateRangeDetectionForDataSourceCommand extends ContainerAwareCommand
{
    /** @var  Logger */
    private $logger;

    /** @var  PublisherManagerInterface */
    private $publisherManager;

    /** @var  DataSourceManagerInterface */
    protected $dataSourceManager;

    const COMMAND_NAME = 'ur:data-source:force-date-range-detection';
    const OPTION_ALL = 'all';
    const OPTION_PUBLISHER = 'publisher';
    const OPTION_DATA_SOURCE = 'data-source';

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addOption(self::OPTION_ALL, 'a', InputOption::VALUE_NONE,
                'Apply for all data sources')
            ->addOption(self::OPTION_PUBLISHER, 'p', InputOption::VALUE_OPTIONAL,
                'Publisher id, apply for all data sources belong to a publisher')
            ->addOption(self::OPTION_DATA_SOURCE, 'd', InputOption::VALUE_OPTIONAL,
                'Data source id, apply for a data source');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');
        $this->publisherManager = $container->get('ur_user.domain_manager.publisher');
        $this->dataSourceManager = $container->get('ur.domain_manager.data_source');

        $dataSources = $this->getDataSourcesFromInput($input, $output);
        if (empty($dataSources)) {
            $output->writeln('No data sources found. Quit command');
            return;
        }

        $this->logger->info('starting command...');

        /** Get services */
        $workerManager = $container->get('ur.worker.manager');

        $count = 0;
        foreach ($dataSources as $dataSource) {
            if (!$dataSource instanceof DataSourceInterface || !$dataSource->isDateRangeDetectionEnabled()) {
                continue;
            }

            $workerManager->updateDateRangeForDataSource($dataSource->getId());
            $this->logger->info(sprintf('Create worker job update date range for data source "%s", id %s', $dataSource->getName(), $dataSource->getId()));
            $count++;
        }

        $this->logger->info(sprintf('Command run successfully for %s data sources', $count));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return DataSourceInterface[]|null
     */
    private function getDataSourcesFromInput(InputInterface $input, OutputInterface $output)
    {
        $isAllDataSources = $input->getOption(self::OPTION_ALL);
        $publisherId = $input->getOption(self::OPTION_PUBLISHER);
        $dataSourceId = $input->getOption(self::OPTION_DATA_SOURCE);

        /** Do not allow empty option */
        if (!$isAllDataSources && !$publisherId && !$dataSourceId) {
            $output->writeln('Use one option as -a, -p or -d');
            return null;
        }

        /** Do not allow multiple option */
        if (
            ($isAllDataSources && $publisherId) ||
            ($isAllDataSources && $dataSourceId) ||
            ($dataSourceId && $publisherId)
        ) {
            $output->writeln('Use one option as -a, -p or -d');
            return null;
        }

        /** Allow 1 option */

        if ($isAllDataSources) {
            return $this->dataSourceManager->all();
        }

        if ($publisherId) {
            $publisher = $this->publisherManager->find($publisherId);
            if (!$publisher instanceof PublisherInterface) {
                $output->writeln(sprintf('Publisher %s is not exist', $publisherId));
                return null;
            }

            return $this->dataSourceManager->getDataSourceForPublisher($publisher);
        }

        if ($dataSourceId) {
            $dataSource = $this->dataSourceManager->find($dataSourceId);
            if (!$dataSource instanceof DataSourceInterface) {
                $output->writeln(sprintf('Data source %s is not exist', $dataSourceId));
                return null;
            }

            return [$dataSource];
        }

        return null;
    }
}