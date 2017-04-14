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

        $schema = new Schema();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());;
        $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());

        // check if table not existed
        if (!$dataTable) {
            return;
        }

        $delCols = [];
        $addCols = [];
        foreach ($deletedColumns as $deletedColumn => $type) {
            $delCol = $dataTable->getColumn($deletedColumn);
            $delCols[] = $delCol;
            $dataTable->dropColumn($deletedColumn);
            if ($type == FieldType::DATE || $type == FieldType::DATETIME) {
                $dataTable->dropColumn(sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $deletedColumn));
                $dataTable->dropColumn(sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $deletedColumn));
                $dataTable->dropColumn(sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $deletedColumn));
            }
        }

        foreach ($newColumns as $newColumn => $type) {
            if (strcmp($type, FieldType::NUMBER) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, Type::INTEGER, ["notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::DECIMAL) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["precision" => 25, "scale" => 12, "notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::MULTI_LINE_TEXT) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, FieldType::TEXT, ["notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::DATE) === 0 OR strcmp($type, FieldType::DATETIME) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, FieldType::DATE, ["notnull" => false, "default" => null]);
                $addCols[] = $dataTable->addColumn(sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
                $addCols[] = $dataTable->addColumn(sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
                $addCols[] = $dataTable->addColumn(sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $newColumn), Type::INTEGER, ["notnull" => false, "default" => null]);
            } else {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["notnull" => false, "default" => null]);
            }
        }

        $updateTable = new TableDiff($dataTable->getName(), $addCols, [], $delCols, [], [], []);
        $dataSetSynchronizer->syncSchema($schema);
        $alterSqls = $conn->getDatabasePlatform()->getAlterTableSQL($updateTable);
        foreach ($updateColumns as $oldName => $newName) {
            $curCol = $dataTable->getColumn($oldName);
            $sql = sprintf("ALTER TABLE %s CHANGE %s %s %s", $dataTable->getName(), $oldName, $newName, $curCol->getType()->getName());
            if (strtolower($curCol->getType()->getName()) === strtolower(FieldType::NUMBER) || strtolower($curCol->getType()->getName()) === strtolower(FieldType::DECIMAL)) {
                $sql .= sprintf("(%s,%s)", $curCol->getPrecision(), $curCol->getScale());
            }
            $alterSqls[] = $sql;

            if ($curCol->getType()->getName() == FieldType::DATE || $curCol->getType()->getName() == FieldType::DATETIME) {
                $oldDay = sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $oldName);
                $oldMonth = sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $oldName);
                $oldYear = sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $oldName);

                $newDay = sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $newName);
                $newMonth = sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $newName);
                $newYear = sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $newName);

                if ($dataTable->hasColumn($oldDay)) {
                    $sqlDay = sprintf("ALTER TABLE %s CHANGE %s %s %s", $dataTable->getName(), $oldDay, $newDay, Type::INTEGER);
                    $alterSqls[] = $sqlDay;
                }

                if ($dataTable->hasColumn($oldMonth)) {
                    $sqlMonth = sprintf("ALTER TABLE %s CHANGE %s %s %s", $dataTable->getName(), $oldMonth, $newMonth, Type::INTEGER);
                    $alterSqls[] = $sqlMonth;
                }

                if ($dataTable->hasColumn($oldYear)) {
                    $sqlYear = sprintf("ALTER TABLE %s CHANGE %s %s %s", $dataTable->getName(), $oldYear, $newYear, Type::INTEGER);
                    $alterSqls[] = $sqlYear;
                }
            }
        }

        foreach ($alterSqls as $alterSql) {
            $conn->exec($alterSql);
        }
    }
}