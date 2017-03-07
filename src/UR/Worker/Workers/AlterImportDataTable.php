<?php

namespace UR\Worker\Workers;


use Doctrine\DBAL\Schema\Comparator;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\ORM\EntityManagerInterface;
use stdClass;
use UR\DomainManager\DataSetManagerInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DataSet\FieldType;
use UR\Service\DataSet\Locator;
use UR\Service\DataSet\Synchronizer;

class AlterImportDataTable
{
    /**
     * @var DataSetManagerInterface $dataSetManager
     */
    private $dataSetManager;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    private $conn;

    /**
     * AlterImportDataTable constructor.
     * @param DataSetManagerInterface $dataSetManager
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(DataSetManagerInterface $dataSetManager, EntityManagerInterface $entityManager)
    {
        $this->dataSetManager = $dataSetManager;
        $this->entityManager = $entityManager;
        $this->conn = $entityManager->getConnection();
    }

    public function alterDataSetTable(StdClass $params)
    {
        $dataSetId = $params->dataSetId;
        /**
         * @var DataSetInterface $dataSet
         */
        $dataSet = $this->dataSetManager->find($dataSetId);

        if ($dataSet === null) {
            throw new \Exception(sprintf('Cannot find Data Set with id: %s', $dataSetId));
        }

        $deletedColumns = $params->deletedColumns === null ? [] : $params->deletedColumns;
        $updateColumns = $params->updateColumns === null ? [] : $params->updateColumns;
        $newColumns = $params->newColumns === null ? [] : $params->newColumns;

        $schema = new Schema();
        $dataSetLocator = new Locator($this->conn);
        $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());;
        $dataTable = $dataSetLocator->getDataSetImportTable($dataSet->getId());

        // check if table not existed
        if (!$dataTable) {
            return;
        }

        $delCols = [];
        $addCols = [];
        $editCols = [];
        foreach ($deletedColumns as $deletedColumn => $type) {
            $delCols[] = $dataTable->getColumn($deletedColumn);
            $dataTable->dropColumn($deletedColumn);
        }

        foreach ($newColumns as $newColumn => $type) {
            if (strcmp($type, FieldType::NUMBER) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, "decimal", ["precision" => 20, "scale" => 0, "notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::DECIMAL) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["precision" => 25, "scale" => 12, "notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::MULTI_LINE_TEXT) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, FieldType::TEXT, ["notnull" => false, "default" => null]);
            } else if (strcmp($type, FieldType::DATE) === 0) {
                $addCols[] = $dataTable->addColumn($newColumn, FieldType::DATE, ["notnull" => false, "default" => null]);
            } else {
                $addCols[] = $dataTable->addColumn($newColumn, $type, ["notnull" => false, "default" => null]);
            }
        }

        $updateTable = new TableDiff($dataTable->getName(), $addCols, $editCols, $delCols, [], [], []);
        try {
            $dataSetSynchronizer->syncSchema($schema);
            $alterSqls = $this->conn->getDatabasePlatform()->getAlterTableSQL($updateTable);
            foreach ($updateColumns as $oldName => $newName) {
                $curCol = $dataTable->getColumn($oldName);
                $sql = sprintf("ALTER TABLE %s CHANGE %s %s %s", $dataTable->getName(), $oldName, $newName, $curCol->getType()->getName());
                if (strtolower($curCol->getType()->getName()) === strtolower(FieldType::NUMBER) || strtolower($curCol->getType()->getName()) === strtolower(FieldType::DECIMAL)) {
                    $sql .= sprintf("(%s,%s)", $curCol->getPrecision(), $curCol->getScale());
                }
                $alterSqls[] = $sql;
            }

            foreach ($alterSqls as $alterSql) {
                $this->conn->exec($alterSql);
            }

        } catch (\Exception $e) {
            throw new \mysqli_sql_exception("Cannot Sync Schema " . $schema->getName());
        }
    }
}