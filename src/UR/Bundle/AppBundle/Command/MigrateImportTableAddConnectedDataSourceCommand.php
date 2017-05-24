<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\Synchronizer;
use UR\Service\StringUtilTrait;

class MigrateImportTableAddConnectedDataSourceCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:migrate:import-table:add-connected-data-source-id')
            ->setDescription('Add hidden column __connected_data_source_id for data import table if not exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('starting command...');

        /** @var IntegrationManagerInterface $integrationManager */
        $dataSetManager = $container->get('ur.domain_manager.data_set');

        /** @var DataSetInterface[] $dataSets */
        $dataSets = $dataSetManager->all();

        $em = $container->get('doctrine.orm.entity_manager');
        $conn = $em->getConnection();
        $schema = new Schema();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        /** @var DataSetInterface[] $dataSetNeedMigrations */
        $dataSetNeedMigrations = [];
        foreach ($dataSets as $dataSet) {
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
            if (!$dataTable) {
                continue;
            }

            if ($dataTable->hasColumn(DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN)) {
                continue;
            }

            $dataSetNeedMigrations[] = $dataSet;
        }

        foreach ($dataSetNeedMigrations as $dataSetNeedMigration) {
            $dataSetTable = $dataSetSynchronizer->getDataSetImportTable($dataSetNeedMigration->getId());
            $addCols = [];
            // adding columns
            $addCols[] = $dataSetTable->addColumn(DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN, Type::INTEGER, array('unsigned' => true, 'notnull' => true));

            $updateTable = new TableDiff($dataSetTable->getName(), $addCols, [], [], [], [], []);

            try {
                $dataSetSynchronizer->syncSchema($schema);
                $alterSqls = $conn->getDatabasePlatform()->getAlterTableSQL($updateTable);
                foreach ($alterSqls as $alterSql) {
                    $alterSql .= sprintf(' AFTER %s', DataSetInterface::DATA_SOURCE_ID_COLUMN);
                    $conn->exec($alterSql);
                }
            } catch (\Exception $e) {
                throw new \Exception("Cannot Sync Schema " . $schema->getName());
            }

            $connectedDataSourceNeedToMigrations = [];
            $dataSourceIdNeedToRemoves = [];
            foreach ($dataSetNeedMigration->getConnectedDataSources() as $connectedDataSource) {
                $dataSourceId = $connectedDataSource->getDataSource()->getId();
                if (array_key_exists($dataSourceId, $connectedDataSourceNeedToMigrations)) {
                    $dataSourceIdNeedToRemoves[] = $dataSourceId;
                } else {
                    $connectedDataSourceNeedToMigrations[$dataSourceId] = $connectedDataSource;
                }
            }

            /**
             * @var $connectedDataSourceNeedToMigrations ConnectedDataSourceInterface[]
             */
            foreach ($connectedDataSourceNeedToMigrations as $connectedDataSourceNeedToMigration) {
                $dataSourceId = $connectedDataSourceNeedToMigration->getDataSource()->getId();
                if (in_array($connectedDataSourceNeedToMigration->getDataSource()->getId(), $dataSourceIdNeedToRemoves)) {
                    unset($connectedDataSourceNeedToMigrations[$dataSourceId]);
                    continue;
                }

                $updateQb = $conn->createQueryBuilder();
                $updateQb->update($dataSetTable->getName(), 't')->set(DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN, "'" . $connectedDataSourceNeedToMigration->getId() . "'");
                $updateQb->where('t.__data_source_id=:dataSourceId')->setParameter(':dataSourceId', $dataSourceId);
                $updateQb->execute();
            }
        }

        $this->logger->info(sprintf('command run successfully'));
    }
}