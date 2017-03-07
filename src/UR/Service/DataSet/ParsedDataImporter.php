<?php
namespace UR\Service\DataSet;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DTO\Collection;
use UR\Service\Import\ImportDataException;

class ParsedDataImporter
{
    /**
     * @var Connection
     */
    private $conn;
    private $batchSize;
    private static $restrictedColumns = [
        DataSetInterface::ID_COLUMN,
        DataSetInterface::DATA_SOURCE_ID_COLUMN,
        DataSetInterface::IMPORT_ID_COLUMN,
        DataSetInterface::UNIQUE_ID_COLUMN,
        DataSetInterface::OVERWRITE_DATE
    ];
    private $preparedInsertCount;

    public function __construct(EntityManagerInterface $em, $batchSize)
    {
        $this->em = $em;
        $this->batchSize = $batchSize;
        $this->conn = $this->em->getConnection();
    }

    public function importParsedDataFromFileToDatabase(Collection $collection, $importId, ConnectedDataSourceInterface $connectedDataSource)
    {
        $dataSetLocator = new Locator($this->conn);
        $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());;
        $table = $dataSetLocator->getDataSetImportTable($connectedDataSource->getDataSet()->getId());
        //create or update empty dataSet table
        if (!$table) {
            $dataSetSynchronizer->createEmptyDataSetTable($connectedDataSource->getDataSet(), $dataSetLocator);
            $table = $dataSetLocator->getDataSetImportTable($connectedDataSource->getDataSet()->getId());
        }

        $tableName = $table->getName();
        $dimensions = $connectedDataSource->getDataSet()->getDimensions();
        $rows = $collection->getRows();
        $columns = $collection->getColumns();

        foreach ($columns as $k => $column) {
            if (in_array($column, self::$restrictedColumns, true)) {
                throw new \InvalidArgumentException(sprintf('%s cannot be used as a column name. It is reserved for internal use.', $column));
            }
            if (!preg_match('#[_a-z]+#i', $column)) {
                throw new \InvalidArgumentException(sprintf('column names can only contain alpha characters and underscores'));
            }
        }

        if (!is_array($rows) || count($rows) < 1) {
            return true;
        }

        $isOverwriteData = $connectedDataSource->getDataSet()->getAllowOverwriteExistingData();
        $insert_values = array();
        $columns[DataSetInterface::DATA_SOURCE_ID_COLUMN] = DataSetInterface::DATA_SOURCE_ID_COLUMN;
        $columns[DataSetInterface::IMPORT_ID_COLUMN] = DataSetInterface::IMPORT_ID_COLUMN;
        $columns[DataSetInterface::UNIQUE_ID_COLUMN] = DataSetInterface::UNIQUE_ID_COLUMN;
        $question_marks = [];
        $this->preparedInsertCount = 0;
        foreach ($rows as $row) {
            $uniqueKeys = array_intersect_key($row, $dimensions);
            $uniqueId = md5(implode(":", $uniqueKeys));
            $row[DataSetInterface::DATA_SOURCE_ID_COLUMN] = $connectedDataSource->getDataSource()->getId();
            $row[DataSetInterface::IMPORT_ID_COLUMN] = $importId;
            $row[DataSetInterface::UNIQUE_ID_COLUMN] = $uniqueId;

            if ($isOverwriteData) {
                //update
                $where = sprintf("%s = :%s AND %s IS NULL", DataSetInterface::UNIQUE_ID_COLUMN, DataSetInterface::UNIQUE_ID_COLUMN, DataSetInterface::OVERWRITE_DATE);
                $set = sprintf("%s = :%s", DataSetInterface::OVERWRITE_DATE, DataSetInterface::OVERWRITE_DATE);
                $updateSql = sprintf("UPDATE %s SET %s WHERE %s", $tableName, $set, $where);
                $qb = $this->conn->prepare($updateSql);
                $qb->bindValue(DataSetInterface::OVERWRITE_DATE, date('Y-m-d'));
                $qb->bindValue(DataSetInterface::UNIQUE_ID_COLUMN, $uniqueId);
                $this->preparedInsertCount++;
                try {
                    $qb->execute();
                } catch (\Exception $e) {
                    $this->conn->rollBack();
                    throw new ImportDataException(null, null, null);
                }
            }

            $question_marks[] = '(' . $this->placeholders('?', sizeof($row)) . ')';
            $insert_values = array_merge($insert_values, array_values($row));
            $this->preparedInsertCount++;
            $insertSql = sprintf("INSERT INTO %s (%s) VALUES %s", $tableName, implode(",", $columns), implode(',', $question_marks));
            if ($this->preparedInsertCount === $this->batchSize) {
                $this->preparedInsertCount = 0;
                $this->executeInsert($insertSql, $insert_values);
                $insert_values = [];
                $question_marks = [];
            }
        }

        if ($this->preparedInsertCount > 0 && is_array($columns) && is_array($question_marks)) {
            $this->executeInsert($insertSql, $insert_values);
        }

        return true;
    }

    private function placeholders($text, $count = 0, $separator = ",")
    {
        $result = array();
        if ($count > 0) {
            for ($x = 0; $x < $count; $x++) {
                $result[] = $text;
            }
        }

        return implode($separator, $result);
    }

    private function executeInsert($sql, array $values)
    {
        $this->conn->beginTransaction();
        $this->conn->commit(); //commit updates fields

        $this->conn->beginTransaction();
        $stmt = $this->conn->prepare($sql);
        try {
            $stmt->execute($values);
        } catch (\Exception $ex) {
            $this->conn->rollBack();
            throw new ImportDataException(null, null, null);
        }
        $this->conn->commit();
        $this->conn->close();
    }
}