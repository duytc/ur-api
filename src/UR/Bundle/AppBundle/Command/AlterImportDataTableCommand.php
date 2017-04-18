<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
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
    const SQL_TYPE_LONGTEXT = 'longtext';
    const SQL_TYPE_TEXT = 'text';

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:alter:import:data:table')
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
            $curCol = $dataTable->getColumn($oldName);
            $type = $curCol->getType()->getName();
            if ($type === FieldType::TEXT && array_key_exists($newName, $columns) && $columns[$newName] === FieldType::MULTI_LINE_TEXT) {
                $type = self::SQL_TYPE_LONGTEXT;
            } else if ($type === FieldType::TEXT && array_key_exists($newName, $columns) && $columns[$newName] === FieldType::TEXT) {
                $type = self::SQL_TYPE_TEXT;
            }

            if ($dataTable->hasColumn($oldName)) {
                $sql = sprintf("ALTER TABLE %s CHANGE `%s` `%s` %s", $dataTable->getName(), $oldName, $newName, $type);
                if (strtolower($curCol->getType()->getName()) === strtolower(FieldType::NUMBER) || strtolower($curCol->getType()->getName()) === strtolower(FieldType::DECIMAL)) {
                    $sql .= sprintf("(%s,%s)", $curCol->getPrecision(), $curCol->getScale());
                }
                $renameColumnsSqls[] = $sql;
            }

            if ($curCol->getType()->getName() == FieldType::DATE || $curCol->getType()->getName() == FieldType::DATETIME) {
                $oldDay = sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $oldName);
                $oldMonth = sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $oldName);
                $oldYear = sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $oldName);

                $newDay = sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $newName);
                $newMonth = sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $newName);
                $newYear = sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $newName);

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
                $delCols[] = $dataTable->getColumn(sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $deletedColumn));
                $delCols[] = $dataTable->getColumn(sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $deletedColumn));
                $delCols[] = $dataTable->getColumn(sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $deletedColumn));
            }
        }

        $deletedColumnsTable = new TableDiff($dataTable->getName(), [], [], $delCols);
        $deleteColumnsSqls = $conn->getDatabasePlatform()->getAlterTableSQL($deletedColumnsTable);
        foreach ($deleteColumnsSqls as $deleteColumnsSql) {
            $conn->exec($deleteColumnsSql);
        }

        $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());
        foreach ($newColumns as $newColumn => $type) {
            if (strcmp($type, FieldType::NUMBER) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, Type::INTEGER, ["notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::DECIMAL) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["precision" => 25, "scale" => 12, "notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::MULTI_LINE_TEXT) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, Type::TEXT, ["notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::TEXT) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, Type::TEXT, ["notnull" => false, "default" => null, "length" => Synchronizer::TEXT_TYPE_LENGTH]);
            } else if (strcmp($type, FieldType::DATE) === 0 OR strcmp($type, FieldType::DATETIME) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, FieldType::DATE, ["notnull" => false, "default" => null]);
                $addCols[] = $dataTable->addColumn(sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
                $addCols[] = $dataTable->addColumn(sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
                $addCols[] = $dataTable->addColumn(sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
            } else {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["notnull" => false, "default" => null]);
            }
        }

        $addedColumnsTable = new TableDiff($dataTable->getName(), $addCols);
        $addColumnsSqls = $conn->getDatabasePlatform()->getAlterTableSQL($addedColumnsTable);

        // execute add or delete columns sql
        foreach ($addColumnsSqls as $addColumnsSql) {
            $conn->exec($addColumnsSql);
        }
    }
}