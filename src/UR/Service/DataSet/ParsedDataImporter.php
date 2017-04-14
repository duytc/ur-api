<?php
namespace UR\Service\DataSet;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use UR\Service\DTO\Collection;

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

    private $lockingDatabaseTable;

    /**
     * ParsedDataImporter constructor.
     * @param EntityManagerInterface $em
     * @param $batchSize
     */
    public function __construct(EntityManagerInterface $em, $batchSize)
    {
        $this->batchSize = $batchSize;
        $this->conn = $em->getConnection();
        $this->lockingDatabaseTable = new LockingDatabaseTable($this->conn);
    }

    /**
     * @param Collection $collection
     * @param $importId
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return bool
     * @throws Exception
     */
    public function importParsedDataFromFileToDatabase(Collection $collection, $importId, ConnectedDataSourceInterface $connectedDataSource)
    {
        $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());

        //create or get dataSet table
        $table = $dataSetSynchronizer->createEmptyDataSetTable($connectedDataSource->getDataSet());
        $tableName = $table->getName();

        try {
            $this->lockingDatabaseTable->lockTable($tableName);

            $dimensions = $connectedDataSource->getDataSet()->getDimensions();
            $metrics = $connectedDataSource->getDataSet()->getMetrics();
            $rows = $collection->getRows();
            $columns = $collection->getColumns();
            $dateColumns = [];
            foreach ($columns as $k => $column) {
                if (in_array($column, self::$restrictedColumns, true)) {
                    throw new \InvalidArgumentException(sprintf('%s cannot be used as a column name. It is reserved for internal use.', $column));
                }

                if (!preg_match('#[_a-z]+#i', $column)) {
                    throw new \InvalidArgumentException(sprintf('column names can only contain alpha characters and underscores'));
                }

                if ((array_key_exists($column, $dimensions) && ($dimensions[$column] === FieldType::DATE || $dimensions[$column] === FieldType::DATETIME)) ||
                    (array_key_exists($column, $metrics) && ($metrics[$column] === FieldType::DATE || $metrics[$column] === FieldType::DATETIME))
                ) {
                    $dateColumns[] = $column;
                    $columns[] = sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $column);
                    $columns[] = sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $column);
                    $columns[] = sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $column);
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

            foreach ($rows as &$row) {
                if (!is_array($row)) {
                    continue;
                }

                $this->insertMonthYearAt($dateColumns, $row);
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
                    $qb->bindValue(DataSetInterface::OVERWRITE_DATE, date('Y-m-d H:i:sP'));
                    $qb->bindValue(DataSetInterface::UNIQUE_ID_COLUMN, $uniqueId);
                    $this->preparedInsertCount++;

                    $qb->execute();
                }

                $question_marks[] = '(' . $this->placeholders('?', sizeof($row)) . ')';
                $insert_values = array_merge($insert_values, array_values($row));
                $this->preparedInsertCount++;
                $insertSql = sprintf("INSERT INTO %s (`%s`) VALUES %s", $tableName, implode("`,`", $columns), implode(',', $question_marks));
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

            $this->lockingDatabaseTable->unLockTable();

            return true;
        } catch (Exception $exception) {
            $this->conn->rollBack();
            $this->lockingDatabaseTable->unLockTable();
            throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    private function insertMonthYearAt(array $indexes, &$row)
    {
        foreach ($indexes as $index) {
            $date = DateTime::createFromFormat('Y-m-d', $row[$index]);
            $dateTime = DateTime::createFromFormat('Y-m-d H:i:s', $row[$index]);
            $date = $date ? $date : $dateTime;

            if ($date instanceof DateTime) {
                $month = $date->format('n');
                $year = $date->format('Y');
                $day = $date->format('j');
            } else {
                $day = null;
                $month = null;
                $year = null;
            }

            $row[sprintf(Synchronizer::DAY_FIELD_TEMPLATE, $index)] = $day;
            $row[sprintf(Synchronizer::MONTH_FIELD_TEMPLATE, $index)] = $month;
            $row[sprintf(Synchronizer::YEAR_FIELD_TEMPLATE, $index)] = $year;
        }
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
        $stmt->execute($values);

        $this->conn->commit();
        $this->conn->close();
    }
}