<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\Synchronizer;
use UR\Service\StringUtilTrait;

class AlterImportDataTableCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:internal:data-import-table:alter')
            ->addArgument('alterTableConfigFile', InputOption::VALUE_REQUIRED, 'alterTableConfigFile')
            ->setDescription('Alter data import table');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configFile = $input->getArgument('alterTableConfigFile');

        if (!file_exists($configFile)) {
            echo $configFile . " does not exist\n";
            exit(1);
        }

        $rawConfig = json_decode(file_get_contents($configFile), true);
        $dataSetId = $rawConfig['dataSetId'];

        $dataSetManager = $this->getContainer()->get('ur.domain_manager.data_set');
        /**
         * @var Logger $logger
         */
        $logger = $this->getContainer()->get('logger');

        /**
         * @var  EntityManagerInterface $entityManager
         */
        $entityManager = $this->getContainer()->get('doctrine.orm.entity_manager');
        $conn = $entityManager->getConnection();

        /**
         * @var DataSetInterface $dataSet
         */
        $dataSet = $dataSetManager->find($dataSetId);

        if ($dataSet === null) {
            throw new \Exception(sprintf('Cannot find Data Set with id: %s', $dataSetId));
        }

        $deletedColumns = $rawConfig['deletedColumns'];
        $updateColumns = $rawConfig['updateColumns'];
        $newColumns = $rawConfig['newColumns'];

        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());;
        $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());

        // check if table not existed
        if (!$dataTable) {
            return;
        }

        // do rename columns before add or delete column
        $renameColumnsSqls = [];
        $columns = $dataSet->getAllDimensionMetrics();
        foreach ($updateColumns as $oldName => $newName) {
            if (!$dataTable->hasColumn($oldName)) {
                // make sure old column existing. It may be not exist if data set is not sync
                continue;
            }

            $curCol = $dataTable->getColumn($oldName);
            $type = $curCol->getType()->getName();

            // map DBAL type to our custom type
            // e.g type string => type largeText if new column type is largeText
            // e.g type string => type text if new column type is text
            if ($type === Type::STRING && array_key_exists($newName, $columns) && $columns[$newName] === FieldType::LARGE_TEXT) {
                $type = FieldType::LARGE_TEXT;
            } else if ($type === Type::STRING && array_key_exists($newName, $columns) && $columns[$newName] === FieldType::TEXT) {
                $type = FieldType::TEXT;
            }

            if ($dataTable->hasColumn($oldName)) {
                // build type-with-length as native sql type depend on our custom type
                // e.g type=largeText => native sql is ...varchar(65535)
                // e.g type=text => native sql is ...varchar(512)
                $typeWithLength = ($type === FieldType::LARGE_TEXT)
                    ? sprintf('%s(%s)', FieldType::$MAPPED_FIELD_TYPE_DATABASE_TYPE[FieldType::LARGE_TEXT], Synchronizer::FIELD_LENGTH_LARGE_TEXT)
                    : (($type === FieldType::TEXT)
                        ? sprintf('%s(%s)', FieldType::$MAPPED_FIELD_TYPE_DATABASE_TYPE[FieldType::LARGE_TEXT], Synchronizer::FIELD_LENGTH_TEXT)
                        : $type
                    );

                $sql = sprintf("ALTER TABLE %s CHANGE `%s` `%s` %s", $dataTable->getName(), $oldName, $newName, $typeWithLength);
                if (strtolower($curCol->getType()->getName()) === strtolower(FieldType::NUMBER) || strtolower($curCol->getType()->getName()) === strtolower(FieldType::DECIMAL)) {
                    $sql .= sprintf("(%s,%s)", $curCol->getPrecision(), $curCol->getScale());
                }
                $renameColumnsSqls[] = $sql;
            }

            if ($curCol->getType()->getName() == FieldType::DATE || $curCol->getType()->getName() == FieldType::DATETIME) {
                $oldDay = Synchronizer::getHiddenColumnDay($oldName);
                $oldMonth = Synchronizer::getHiddenColumnMonth($oldName);
                $oldYear = Synchronizer::getHiddenColumnYear($oldName);

                $newDay = Synchronizer::getHiddenColumnDay($newName);
                $newMonth = Synchronizer::getHiddenColumnMonth($newName);
                $newYear = Synchronizer::getHiddenColumnYear($newName);

                if ($dataTable->hasColumn($oldDay)) {
                    $sqlDay = sprintf("ALTER TABLE %s CHANGE `%s` `%s` %s", $dataTable->getName(), $oldDay, $newDay, Type::INTEGER);
                    $renameColumnsSqls[] = $sqlDay;
                }

                if ($dataTable->hasColumn($oldMonth)) {
                    $sqlMonth = sprintf("ALTER TABLE %s CHANGE `%s` `%s` %s", $dataTable->getName(), $oldMonth, $newMonth, Type::INTEGER);
                    $renameColumnsSqls[] = $sqlMonth;
                }

                if ($dataTable->hasColumn($oldYear)) {
                    $sqlYear = sprintf("ALTER TABLE %s CHANGE `%s` `%s` %s", $dataTable->getName(), $oldYear, $newYear, Type::INTEGER);
                    $renameColumnsSqls[] = $sqlYear;
                }
            }
        }

        // execute rename sql
        foreach ($renameColumnsSqls as $alterSql) {
            $conn->exec($alterSql);
        }

        //get table again after rename columns
        $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());

        $delCols = [];
        $addCols = [];
        foreach ($deletedColumns as $deletedColumn => $type) {
            $delCols[] = $dataTable->getColumn($deletedColumn);
            if ($type == FieldType::DATE || $type == FieldType::DATETIME) {
                $delCols[] = $dataTable->getColumn(Synchronizer::getHiddenColumnDay($deletedColumn));
                $delCols[] = $dataTable->getColumn(Synchronizer::getHiddenColumnMonth($deletedColumn));
                $delCols[] = $dataTable->getColumn(Synchronizer::getHiddenColumnYear($deletedColumn));
            }
        }

        $deletedColumnsTable = new TableDiff($dataTable->getName(), [], [], $delCols);
        $deleteColumnsSqls = $conn->getDatabasePlatform()->getAlterTableSQL($deletedColumnsTable);
        foreach ($deleteColumnsSqls as $deleteColumnsSql) {
            $conn->exec($deleteColumnsSql);
        }

        $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
        foreach ($newColumns as $newColumn => $type) {
            if ($type === FieldType::NUMBER) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$type];
                $addCols[] = $dataTable->addColumn($newColumn, $colType, ['notnull' => false, 'default' => null]);
            } else if ($type === FieldType::DECIMAL) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$type];
                $addCols[] = $dataTable->addColumn($newColumn, $colType, ['precision' => 25, 'scale' => 12, 'notnull' => false, 'default' => null]);
            } else if ($type === FieldType::LARGE_TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$type];
                $addCols[] = $dataTable->addColumn($newColumn, $colType, ['notnull' => false, 'default' => null, 'length' => Synchronizer::FIELD_LENGTH_LARGE_TEXT]);
            } else if ($type === FieldType::TEXT) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$type];
                $addCols[] = $dataTable->addColumn($newColumn, $colType, ['notnull' => false, 'default' => null, 'length' => Synchronizer::FIELD_LENGTH_TEXT]);
            } else if ($type === FieldType::DATE OR $type === FieldType::DATETIME) {
                $colType = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[$type];
                $addCols[] = $dataTable->addColumn($newColumn, $colType, ['notnull' => false, 'default' => null]);

                $colTypeDayOrMonthOrYear = FieldType::$MAPPED_FIELD_TYPE_DBAL_TYPE[FieldType::NUMBER];
                $addCols[] = $dataTable->addColumn(Synchronizer::getHiddenColumnDay($newColumn), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
                $addCols[] = $dataTable->addColumn(Synchronizer::getHiddenColumnMonth($newColumn), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
                $addCols[] = $dataTable->addColumn(Synchronizer::getHiddenColumnYear($newColumn), $colTypeDayOrMonthOrYear, ['notnull' => false, 'default' => null]);
            } else {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ['notnull' => false, 'default' => null]);
            }
        }

        $addedColumnsTable = new TableDiff($dataTable->getName(), $addCols);
        $addColumnsSqls = $conn->getDatabasePlatform()->getAlterTableSQL($addedColumnsTable);

        // execute add or delete columns sql
        foreach ($addColumnsSqls as $addColumnsSql) {
            $conn->exec($addColumnsSql);
        }

        // update indexes
        $dataSetSynchronizer->updateIndexes($conn, $dataTable, $dataSet);
    }
}