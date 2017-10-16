<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\Synchronizer;

class SyncDataSetCommand extends ContainerAwareCommand
{
    Const INPUT_DATA_FORCE = 'force';
    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:data-set:sync')
            ->addArgument('dataSetId', InputArgument::REQUIRED, 'Data set id')
            ->addOption(self::INPUT_DATA_FORCE, 'f',InputOption::VALUE_NONE,'Execute sql query')
            ->setDescription('Synchronization dataSet with data_import_ table');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        $this->logger->info('Starting command...');

        /* get inputs */
        $dataSetId = $input->getArgument('dataSetId');

        if (empty($dataSetId)) {
            $this->logger->warning('Missing data set id');
            return;
        }

        $isForceRun = $input->getOption('force');

        /** @var IntegrationManagerInterface $integrationManager */
        $dataSetManager = $container->get('ur.domain_manager.data_set');

        /** @var DataSetInterface[] $dataSets */
        $dataSet = $dataSetManager->find($dataSetId);

        if (!$dataSet instanceof DataSetInterface) {
            $this->logger->info(sprintf('DataSet does not exist. Please check your config.'));
            return;
        }

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $conn = $em->getConnection();

        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dataSetTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());

        $dimensions = $dataSet->getDimensions();
        $metrics = $dataSet->getMetrics();
        $mergeDimensionsAndMetrics = array_merge($dimensions, $metrics);

        // get all columns
        $allColumnsCurrent = $dataSetTable->getColumns();
        //keep default columns; remove columns of dimensions, metrics and the columns do not use
        foreach ($allColumnsCurrent as $key => $value){
            $columnName = $value->getName();
            $columnType = $value->getType();
            if (preg_match('/__/', $columnName)) {
                continue;
            } else {
                $dataSetTable->dropColumn($columnName);
                if ($columnType instanceof  DateTimeType || $columnType instanceof DateType) {
                    if($dataSetTable->hasColumn(Synchronizer::getHiddenColumnDay($columnName))){
                        $dataSetTable->dropColumn(Synchronizer::getHiddenColumnDay($columnName));
                    }

                    if($dataSetTable->hasColumn(Synchronizer::getHiddenColumnMonth($columnName))){
                        $dataSetTable->dropColumn(Synchronizer::getHiddenColumnMonth($columnName));
                    }

                    if($dataSetTable->hasColumn(Synchronizer::getHiddenColumnYear($columnName))){
                        $dataSetTable->dropColumn(Synchronizer::getHiddenColumnYear($columnName));
                    }
                }
            }
        }

        // add columns dimensions and metrics
        foreach ($mergeDimensionsAndMetrics as $field => $fieldType) {
            if ($fieldType === FieldType::NUMBER) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetTable->addColumn($field, $colType, ['notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::DECIMAL) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetTable->addColumn($field, $colType, ['precision' => 25, 'scale' => 12, 'notnull' => false, 'default' => null]);
            } else if ($fieldType === FieldType::LARGE_TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetTable->addColumn($field, $colType, ['notnull' => false, 'default' => null, 'length' => Synchronizer::FIELD_LENGTH_LARGE_TEXT]);
            } else if ($fieldType === FieldType::TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetTable->addColumn($field, $colType, ['notnull' => false, 'default' => null, 'length' => Synchronizer::FIELD_LENGTH_TEXT]);
            } else if ($fieldType === FieldType::DATE || $fieldType === FieldType::DATETIME) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$fieldType];
                $dataSetTable->addColumn($field, $colType, ['notnull' => false, 'default' => null]);
                $colTypeDayOrMonthOrYear = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[FieldType::NUMBER];
                $dataSetTable->addColumn(Synchronizer::getHiddenColumnDay($field), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
                $dataSetTable->addColumn(Synchronizer::getHiddenColumnMonth($field), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
                $dataSetTable->addColumn(Synchronizer::getHiddenColumnYear($field), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
            } else {
                $dataSetTable->addColumn($field, $fieldType, ['notnull' => false, 'default' => null]);
            }
        }

        $tables[] = $dataSetTable;
        $schema = new Schema($tables);

        // get query alter table
        $queries = $dataSetSynchronizer->getSyncSchemaDataSet($schema);

        if (empty($queries)) {
            $this->logger->info(sprintf('There is nothing to sync this dataSet.'));
            return;
        }

        $this->logger->info('Query to synchronize Data Set: ');
        var_dump($queries);

        if ($isForceRun) {
            try {
                $this->logger->info('Executing this query.');

                foreach ($queries as $query) {
                    $conn->exec($query);
                }

                $this->logger->info(sprintf('Finish sync Data Set (Id: %s).', $dataSetId));
            } catch (\Exception $e) {
                throw new \Exception("Cannot Sync Data Set ");
            }
        }
    }
}