<?php

namespace UR\Bundle\AppBundle\Command;

use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\DateTime\DateRangeService;

class ForceDateRangeDetectionCommand extends ContainerAwareCommand
{
    /** @var  PublisherManagerInterface */
    private $publisherManager;

    /** @var  DataSourceManagerInterface */
    protected $dataSourceManager;

    /** @var  DataSourceEntryManagerInterface */
    protected $dataSourceEntryManager;

    /** @var  DateRangeService $dateRangeService */
    protected $dateRangeService;

    /** @var SymfonyStyle */
    private $io;

    const COMMAND_NAME = 'ur:force-date-range-detection';
    const OPTION_ALL = 'all';
    const OPTION_PUBLISHER = 'publisher';
    const OPTION_DATA_SOURCE = 'data-source';
    const OPTION_DATA_SOURCE_ENTRY = 'data-source-entry';

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addOption(self::OPTION_DATA_SOURCE_ENTRY, 'u', InputOption::VALUE_OPTIONAL,
                'Data Source Entry Id, apply for a Data Source Entry and the Data Source relate to')
            ->addOption(self::OPTION_DATA_SOURCE, 'd', InputOption::VALUE_OPTIONAL,
                'Data Source Id, apply for a Data Source and all Data Source Entries relate to')
            ->addOption(self::OPTION_PUBLISHER, 'p', InputOption::VALUE_OPTIONAL,
                'Publisher Id, apply for all Data Sources belong to a Publisher and all Data Source Entries relate to')
            ->setDescription('Force date range detection will be apply for
                                - an entry and dataSource relate to(input is dataSourceEntryId)
                                - an dataSource and all Data Source Entries  relate to(input is dataSourceId)
                                - dataSources and all Data Source Entries  relate to(input is publisherId; dataSources belong to a publisher)
                                - default is all DataSources and all Data Source Entries  relate to');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        $this->io = new SymfonyStyle($input, $output);
        $this->publisherManager = $container->get('ur_user.domain_manager.publisher');
        $this->dataSourceManager = $container->get('ur.domain_manager.data_source');
        $this->dataSourceEntryManager = $container->get('ur.domain_manager.data_source_entry');
        $this->dateRangeService = $container->get('ur.service.date_time.date_range_service');


        $this->io->section('Starting command...');
        /* get inputs */
        $dataSourceEntryId = $input->getOption(self::OPTION_DATA_SOURCE_ENTRY);
        $dataSourceId = $input->getOption(self::OPTION_DATA_SOURCE);
        $publisherId = $input->getOption(self::OPTION_PUBLISHER);

        if (!$this->validateInput($input, $output)) {
            return 0;
        }

        if ($dataSourceEntryId) {
            $this->io->section(sprintf('Input is dataSourceEntryId %d', $dataSourceEntryId));
            $this->forceDateRangeDetectionForDataSourceEntry($dataSourceEntryId);

            return true;
        }

        if ($dataSourceId) {
            $this->io->section(sprintf('Input is dataSourceId %d', $dataSourceId));
            $this->forceDateRangeDetectionForDataSourceId($dataSourceId);

            return true;
        }

        if ($publisherId) {
            $this->io->section(sprintf('Input is $publisherId %d', $publisherId));
            $this->forceDateRangeDetectionByPublisher($publisherId);

            return true;
        }

        if (!$dataSourceEntryId && !$dataSourceId && !$publisherId) {
            $this->io->section('There is no input option. Default will apply for all dataSources');

            $dataSources = $this->dataSourceManager->all();
            if (empty($dataSources)) {
                $output->writeln('No data sources found. Quit command');
                return 0;
            }

            $this->forceDateRangeDetectionForDataSources($dataSources);
        }
    }

    /**
     * @param $dataSourceEntryId
     * @return bool
     */
    private function forceDateRangeDetectionForDataSourceEntry($dataSourceEntryId) {
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            $this->io->warning(sprintf('Data Source Entry %s does not exist', $dataSourceEntryId));

            return false;
        }

        if (!$dataSourceEntry->getDataSource()->isDateRangeDetectionEnabled()) {
            $this->io->warning(sprintf('isDateRangeDetectionEnabled is false - Data Source Entry %s', $dataSourceEntryId));

            return false;
        }

        $this->io->success(sprintf('Do force date range detection for Data Source Entry %s', $dataSourceEntry->getId()));
        $this->dateRangeService->calculateDateRangeForDataSourceEntry($dataSourceEntryId);

        $dataSource = $dataSourceEntry->getDataSource();

        if (!$dataSource instanceof DataSourceInterface) {
            $this->io->warning(sprintf('Data source %s is not exist', $dataSource->getId()));

            return false;
        }


        if (!$dataSource->isDateRangeDetectionEnabled()) {
            $this->io->warning(sprintf('isDateRangeDetectionEnabled is false - Data Source %s', $dataSource->getId()));

            return false;
        }

        $this->io->success(sprintf('Do force date range detection for Data Source %s', $dataSource->getId()));
        $this->dateRangeService->calculateDateRangeForDataSource($dataSource->getId());

        $this->io->success('Command runs successfully.');
    }

    /**
     * @param $dataSourceId
     * @return bool
     */
    private function forceDateRangeDetectionForDataSourceId($dataSourceId) {
        $dataSource = $this->dataSourceManager->find($dataSourceId);
        if (!$dataSource instanceof DataSourceInterface) {
            $this->io->warning(sprintf('Data source %s is not exist', $dataSourceId));

            return false;
        }

        if (!$dataSource->isDateRangeDetectionEnabled()) {
            $this->io->warning(sprintf('isDateRangeDetectionEnabled is false - Data Source %s', $dataSource->getId()));

            return false;
        }

        $this->forceDateRangeDetectionForDataSource($dataSource);

        $this->io->success('Command runs successfully.');
    }

    /**
     * @param $publisherId
     * @return bool
     */
    private function forceDateRangeDetectionByPublisher($publisherId) {
        $publisher = $this->publisherManager->find($publisherId);
        if (!$publisher instanceof PublisherInterface) {
            $this->io->warning(sprintf('Publisher %s is not exist', $publisherId));
            return false;
        }

        $dataSources = $this->dataSourceManager->getDataSourceForPublisher($publisher);

        $this->forceDateRangeDetectionForDataSources($dataSources);
    }

    /**
     * @param $dataSources
     * @return bool
     */
    private function forceDateRangeDetectionForDataSources($dataSources) {
        $count = 0;
        foreach ($dataSources as $dataSource) {
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }

            if (!$dataSource->isDateRangeDetectionEnabled()) {
                $this->io->warning(sprintf('isDateRangeDetectionEnabled is false - Data Source %s', $dataSource->getId()));

                continue;
            }

            $this->forceDateRangeDetectionForDataSource($dataSource);
            $count++;
        }

        $this->io->success(sprintf('Command runs force date range detection for %s data sources', $count));
    }

    /**
     * @param DataSourceInterface $dataSource
     * @return bool
     */
    private function forceDateRangeDetectionForDataSource(DataSourceInterface $dataSource) {
        $dataSourceEntries = $dataSource->getDataSourceEntries();
        foreach ($dataSourceEntries as $dataSourceEntry) {
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                return false;
            }

            $this->io->success(sprintf('Do force date range detection for Data Source Entry %s', $dataSourceEntry->getId()));
            $this->dateRangeService->calculateDateRangeForDataSourceEntry($dataSourceEntry->getId());
        }

        $this->io->success(sprintf('Do force date range detection for Data Source %s', $dataSource->getId()));
        $this->dateRangeService->calculateDateRangeForDataSource($dataSource->getId());
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return bool
     */
    private function validateInput(InputInterface $input, OutputInterface $output)
    {
        $dataSourceEntryId = $input->getOption(self::OPTION_DATA_SOURCE_ENTRY);
        $dataSourceId = $input->getOption(self::OPTION_DATA_SOURCE);
        $publisherId = $input->getOption(self::OPTION_PUBLISHER);

        // validate option, require one of options
        if (($dataSourceEntryId && $dataSourceId)
            || ($dataSourceEntryId && $publisherId)
            || ($dataSourceId && $publisherId)) {
            $this->io->warning(sprintf('command run failed: invalid input info, require only one of options'));

            return false;
        }

        if (!$dataSourceEntryId && !$dataSourceId && !$publisherId) {
            $this->io->warning('There is no input option. Please enter an option and then try again.');

            return false;
        }
        return true;
    }
}