<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\DateTimeType;
use Doctrine\DBAL\Types\DateType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\DataSetTableUtilInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\Synchronizer;
use UR\Service\DTO\DataImportTable\ColumnIndex;

class CreateCustomIndexForDataSetCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:data-set:custom-indexes';
    const INPUT_DATA_SYNC = 'sync';
    const INPUT_DATA_DATA_SET_ID = 'dataSetId';
    const INPUT_DATA_FIELDS = 'fields';
    const REQUIRED_INDEXES = ['primary', 'PRIMARY', 'unique_hash_idx'];

    /** @var  Logger */
    private $logger;

    /** @var  DataSetManagerInterface */
    private $dataSetManager;

    /** @var  DataSetTableUtilInterface */
    private $dataSetTableUtil;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument(self::INPUT_DATA_DATA_SET_ID, InputArgument::REQUIRED, 'Data set id')
            ->addArgument(self::INPUT_DATA_FIELDS, InputOption::VALUE_REQUIRED, 'The fields will be create custom index. Allow multi groups, separated by semicolon (;). Allow multi fields on each group, separated by comma (,) such as impression,revenue;ad_tag_id;revenue,ad_tag_id,impression')
            ->addOption(self::INPUT_DATA_SYNC, 'f', InputOption::VALUE_NONE, 'Execute synchronization dataSet')
            ->setDescription('Create custom indexes for data set tables');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var EntityManagerInterface $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $conn = $em->getConnection();
        $io = new SymfonyStyle($input, $output);
        /* get inputs */
        $dataSetId = $input->getArgument(self::INPUT_DATA_DATA_SET_ID);
        $fields = $input->getArgument(self::INPUT_DATA_FIELDS);
        $sync = $input->getOption(self::INPUT_DATA_SYNC);

        /** Do not allow empty option */
        if (empty($dataSetId) || empty($fields)) {
            $io->warning('Missing DataSetId or the fields.  Please check your config and try again');
            return null;
        }

        /** check dataSetId must be numeric */
        if (!is_numeric($dataSetId)) {
            $io->warning('dataSetId must be a numeric.  Please check your config and try again');
            return null;
        }

        /** Services */
        $this->logger = $container->get('logger');
        $this->dataSetManager = $container->get('ur.domain_manager.data_set');
        $this->dataSetTableUtil = $container->get('ur.service.data_set.table_util');

        $dataSet = $this->dataSetManager->find($dataSetId);

        if (!$dataSet instanceof DataSetInterface) {
            $io->warning(sprintf('DataSet %s is not exist', $dataSetId));
            return null;
        }

        if ($sync) {
            $io->section(sprintf("Execute sync data set table %s, id: %s", $dataSet->getName(), $dataSet->getId()));
            $this->syncDataSet($conn, $dataSet, $output);
        }

        $io->section(sprintf("Create custom index for data set %s, id: %s", $dataSet->getName(), $dataSet->getId()));
        try {
            $this->updateCustomIndexes($conn, $dataSet, $fields, $output);
        } catch (\Exception $e) {

        }

        $io->success('Command run successfully. Quit command');
    }

    /**
     * @param Connection $conn
     * @param DataSetInterface $dataSet
     * @param $output
     * @throws \Exception
     */
    protected function syncDataSet(Connection $conn, DataSetInterface $dataSet, OutputInterface $output)
    {

        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dataSetTable = $dataSetSynchronizer->createEmptyDataSetTable($dataSet);

        if (!$dataSetTable instanceof Table) {
            return;
        }

        $dimensions = $dataSet->getDimensions();
        $metrics = $dataSet->getMetrics();
        $mergeDimensionsAndMetrics = array_merge($dimensions, $metrics);

        // get all columns
        $allColumnsCurrent = $dataSetTable->getColumns();
        //keep default columns; remove columns of dimensions, metrics and the columns do not use
        foreach ($allColumnsCurrent as $key => $value) {
            $columnName = $value->getName();
            $columnType = $value->getType();
            if (preg_match('/__/', $columnName)) {
                continue;
            } else {
                $dataSetTable->dropColumn($columnName);
                if ($columnType instanceof DateTimeType || $columnType instanceof DateType) {
                    if ($dataSetTable->hasColumn(Synchronizer::getHiddenColumnDay($columnName))) {
                        $dataSetTable->dropColumn(Synchronizer::getHiddenColumnDay($columnName));
                    }

                    if ($dataSetTable->hasColumn(Synchronizer::getHiddenColumnMonth($columnName))) {
                        $dataSetTable->dropColumn(Synchronizer::getHiddenColumnMonth($columnName));
                    }

                    if ($dataSetTable->hasColumn(Synchronizer::getHiddenColumnYear($columnName))) {
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
            $output->writeln(sprintf('There is nothing to sync this dataSet.'));
        } else {
            $output->writeln('Query to synchronize Data Set: ');
            var_dump($queries);

            try {
                $output->writeln('Executing this query.');

                foreach ($queries as $query) {
                    $conn->exec($query);
                }

                $output->writeln(sprintf('Finish sync Data Set (Id: %s).', $dataSet->getId()));
            } catch (\Exception $e) {
                throw new \Exception("Cannot Sync Data Set ");
            }
        }
    }

    /**
     * @param Connection $conn
     * @param DataSetInterface $dataSet
     * @param $fields
     * @param OutputInterface $output
     */
    public function updateCustomIndexes(Connection $conn, DataSetInterface $dataSet, $fields, OutputInterface $output)
    {
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());
        $dataSetTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
        $inUsedIndexes = [];
        $columnIndexes = [];
        $customIndexConfigs = [];
        // get all dimensions and metrics
        $dimensionsAndMetrics = $dataSet->getAllDimensionMetrics();
        // check column exist or not
        $multiFields = explode(';', $fields);
        foreach ($multiFields as $multiField) {
            $subFields = explode(',', $multiField);
            $customIndexConfig = $subFields;
            foreach ($subFields as $subField) {
                if ($dataSetTable->hasColumn($subField)) {
                    if (array_key_exists($subField, $dimensionsAndMetrics)) {
                        $columnIndex [] = new ColumnIndex($subField, $dimensionsAndMetrics[$subField]);
                    }
                }
            }
            if (isset($columnIndex) && !empty($columnIndex)) {
                $columnIndexes [] = $columnIndex;
            }

            $customIndexConfigs [] = $customIndexConfig;

            //reset $columnIndex
            $columnIndex = [];
            unset($customIndexConfig);
        }

        // execute prepared statement for creating indexes
        $conn->beginTransaction();

        foreach ($columnIndexes as $multipleColumnIndexes) {
            /** @var ColumnIndex[] $multipleColumnIndexes */
            if (!is_array($multipleColumnIndexes)) {
                continue;
            }

            $columnNamesAndLengths = []; // for building sql create index

            // build index for multiple columns
            foreach ($multipleColumnIndexes as $singleColumnIndex) {
                if (!$singleColumnIndex instanceof ColumnIndex) {
                    continue;
                }

                $columnName = $singleColumnIndex->getColumnName();
                if (!$dataSetTable->hasColumn($columnName)) {
                    continue; // column not found
                }

                $columnLength = $singleColumnIndex->getColumnLength();
                $columnNamesAndLengths[] = (null === $columnLength)
                    ? $columnName
                    : sprintf('%s(%s)', $columnName, $columnLength);
            }

            // sure have columns to be created index
            if (empty($columnNamesAndLengths)) {
                continue;
            }

            $indexName = $dataSetSynchronizer->getDataSetImportTableIndexName($dataSetTable->getName());
            sleep(1);
            // update inUsedIndexes
            $inUsedIndexes[] = $indexName;

            if ($dataSetTable->hasIndex($indexName)) {
                continue; // already has index
            }

            $dataSetSynchronizer->prepareStatementCreateIndex($conn, $indexName, $dataSetTable->getName(), $columnNamesAndLengths);
        }

        try {
            $conn->commit();
        } catch (\Exception $e) {

        }

        // remove non existing indexes
        $conn->beginTransaction();

        $allIndexObjects = $dataSetTable->getIndexes();
        $allIndexes = array_map(function (Index $indexObject) {
            return $indexObject->getName();
        }, $allIndexObjects);

        $nonExistingIndexes = array_diff($allIndexes, $inUsedIndexes);
        foreach ($nonExistingIndexes as $nonExistingIndex) {
            // exclude 'primary' and 'unique_hash_idx' indexes
            if (in_array($nonExistingIndex, self::REQUIRED_INDEXES)) {
                continue;
            }

            $dataSetSynchronizer->prepareStatementDropIndex($conn, $nonExistingIndex, $dataSetTable->getName());
        }

        try {
            $conn->commit();
        } catch (\Exception $e) {

        }

        $output->writeln(sprintf('Save custom index config into Data Set (Id: %s).', $dataSet->getId()));
        $dataSet->setCustomIndexConfig($customIndexConfigs);
        $this->dataSetManager->save($dataSet);
    }
}