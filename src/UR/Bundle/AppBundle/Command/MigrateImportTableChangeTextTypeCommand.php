<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\Synchronizer;
use UR\Service\StringUtilTrait;

class MigrateImportTableChangeTextTypeCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:migrate:import-table:change-text-type')
            ->setDescription('Add column __unique_id for data import table if not exist');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('Starting command...');

        /** @var IntegrationManagerInterface $integrationManager */
        $dataSetManager = $container->get('ur.domain_manager.data_set');

        /** @var DataSetInterface[] $dataSets */
        $dataSets = $dataSetManager->all();

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $conn = $em->getConnection();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());

        $migratedCount = 0;

        foreach ($dataSets as $dataSet) {
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
            if (!$dataTable) {
                continue;
            }

            $migratedCount++;

            // update unique_id from longtext to varchar(64)
            $this->alterTypeForUniqueId($conn, $dataTable);

            // update all dimensions from
            // - text to varchar
            // - longtext to text
            $dimensions = $dataSet->getDimensions();
            $this->alterTypeForAllDimensions($conn, $dataTable, $dimensions);

            // update all metrics from
            // - text to varchar
            // - longtext to text
            $metrics = $dataSet->getMetrics();
            $this->alterTypeForAllMetrics($conn, $dataTable, $metrics);
        }

        // update changes
        $conn->beginTransaction();
        $conn->commit();

        $this->logger->info(sprintf('Command finished successfully: %d data import tables updated.', $migratedCount));
    }

    /**
     * alter Type For All Dimensions
     *
     * @param Connection $conn
     * @param Table $dataTable
     */
    private function alterTypeForUniqueId(Connection $conn, Table $dataTable)
    {
        $columnName = DataSetInterface::UNIQUE_ID_COLUMN;
        if (!$dataTable->hasColumn($columnName)) {
            return;
        }

        $columnType = FieldType::DATABASE_TYPE_UNIQUE_ID;
        $columnLength = Synchronizer::FIELD_LENGTH_COLUMN_UNIQUE_ID;

        // append length to columnType
        $columnType = sprintf('%s(%d)', $columnType, $columnLength);

        $updateSql = sprintf('ALTER TABLE %s MODIFY %s %s;',
            $dataTable->getName(),
            $columnName,
            $columnType
        );

        $stmtCreateIndex = $conn->prepare($updateSql);
        $stmtCreateIndex->execute();
    }

    /**
     * alter Type For All Dimensions
     *
     * @param Connection $conn
     * @param Table $dataTable
     * @param array $dimensions
     */
    private function alterTypeForAllDimensions(Connection $conn, Table $dataTable, array $dimensions)
    {
        foreach ($dimensions as $dimension => $dimensionType) {
            // change dimensionType from longtext to text
            $dimensionType = $dimensionType === 'multiLineText' ? FieldType::LARGE_TEXT : $dimensionType;

            if ($dimensionType !== FieldType::TEXT && $dimensionType !== FieldType::LARGE_TEXT) {
                continue; // only change for text and largeText
            }

            Synchronizer::alterTypeForColumn($conn, $dataTable, $dimension, $dimensionType);
        }
    }

    /**
     * alter Type For All Metrics
     *
     * @param Connection $conn
     * @param Table $dataTable
     * @param array $metrics
     */
    private function alterTypeForAllMetrics(Connection $conn, Table $dataTable, array $metrics)
    {
        foreach ($metrics as $metric => $metricType) {
            // change dimensionType from longtext to text
            $metricType = $metricType === 'multiLineText' ? FieldType::LARGE_TEXT : $metricType;

            if ($metricType !== FieldType::TEXT && $metricType !== FieldType::LARGE_TEXT) {
                continue; // only change for text and largeText
            }

            Synchronizer::alterTypeForColumn($conn, $dataTable, $metric, $metricType);
        }
    }
}