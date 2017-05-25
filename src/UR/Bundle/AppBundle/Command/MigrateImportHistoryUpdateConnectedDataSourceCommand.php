<?php

namespace UR\Bundle\AppBundle\Command;

use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\StringUtilTrait;

class MigrateImportHistoryUpdateConnectedDataSourceCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:migrate:import-history:add-connected-data-source-id')
            ->setDescription('update connected_data_source_id for import history table');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('starting command...');

        /** @var ImportHistoryManagerInterface $importHistoryManager */
        $importHistoryManager = $container->get('ur.domain_manager.import_history');

        /** @var ImportHistoryInterface[] $importHistories */
        $importHistories = $importHistoryManager->all();

        $importHistoryNeedToMigrations = [];
        foreach ($importHistories as $importHistory) {
            if ($importHistory->getConnectedDataSource() != null) {
                continue;
            }

            $importHistoryNeedToMigrations[] = $importHistory;
        }

        /** @var ImportHistoryInterface[] $importHistoryNeedToMigrations */
        foreach ($importHistoryNeedToMigrations as $importHistory) {
            $dataSource = $importHistory->getDataSourceEntry()->getDataSource();
            $dataSet = $importHistory->getDataSet();
            $i = 0;
            $updateConnected = null;
            foreach ($dataSet->getConnectedDataSources() as $connectedDataSource) {
                if ($connectedDataSource->getDataSource()->getId() != $dataSource->getId()) {
                    continue;
                }

                $i++;
                $updateConnected = $connectedDataSource;
            }

//            if ($i > 1) {
//                continue;
//            }
            if ($updateConnected === null) {
                continue;
            }

            $importHistory->setConnectedDataSource($updateConnected);
            $importHistoryManager->save($importHistory);
        }

        $this->logger->info(sprintf('command run successfully'));
    }
}