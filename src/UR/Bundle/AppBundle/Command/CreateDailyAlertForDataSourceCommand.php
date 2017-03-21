<?php

namespace UR\Bundle\AppBundle\Command;

use DateTime;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSourceEntryManager;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Alert\DataSource\DataSourceAlertFactory;
use UR\Service\Alert\DataSource\NoDataReceivedDailyAlert;
use UR\Service\Alert\ProcessAlertInterface;

class CreateDailyAlertForDataSourceCommand extends ContainerAwareCommand
{
    /** @var Logger */
    private $logger;
    const DATA_NOT_RECEIVED_KEY = 'notReceived';

    protected function configure()
    {
        $this
            ->setName('ur:data-source:create-daily-alert')
            ->setDescription('Create alert if data is not received daily');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('starting command...');

        $dataSourceManager = $container->get('ur.domain_manager.data_source');
        /** @var DataSourceEntryManager $dataSourceEntryManager */
        $dataSourceEntryManager = $container->get('ur.domain_manager.data_source_entry');
        /** @var ProcessAlertInterface $processAlert */
        $processAlert = $container->get('ur.service.alert.process_alert');

        $dailyAlertDataSources = $dataSourceManager->getDataSourcesHasDailyAlert();

        $this->logger->info(sprintf('Number of data sources: %d', count($dailyAlertDataSources)));
        /** @var DataSourceInterface[] $dailyAlertDataSources */

        foreach ($dailyAlertDataSources as $dailyAlertDataSource) {
            $publisher = $dailyAlertDataSource->getPublisher();

            $alertFactory = new DataSourceAlertFactory();

            /**
             * @var NoDataReceivedDailyAlert $noDataDailyAlert
             */
            $noDataDailyAlert = $alertFactory->getAlert(NoDataReceivedDailyAlert::ALERT_CODE_NO_DATA_RECEIVED_DAILY, null, $dailyAlertDataSource);
            $dsNextTime = $dailyAlertDataSource->getNextAlertTime();

            $currentDate = new DateTime();
            if (!$noDataDailyAlert instanceof NoDataReceivedDailyAlert || $currentDate < $dsNextTime) {
                continue;
            }

            $todayDataSourceEntries = $dataSourceEntryManager->getDataSourceEntryToday($dailyAlertDataSource, $dsNextTime);

            if (count($todayDataSourceEntries) < 1) {
                $processAlert->createAlert($noDataDailyAlert->getAlertCode(), $publisher->getId(), $noDataDailyAlert->getDetails());
            }

            $dailyAlertDataSource->setNextAlertTime(new DateTime('tomorrow'));
            $dataSourceManager->save($dailyAlertDataSource);
        }

        $this->logger->info(sprintf('command run successfully'));
    }
}