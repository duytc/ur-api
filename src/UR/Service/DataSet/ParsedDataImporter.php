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

    /**
     * @var EntityManagerInterface
     */
    private $em;
    private $batchSize;
    private static $restrictedColumns = [
        DataSetInterface::ID_COLUMN,
        DataSetInterface::DATA_SOURCE_ID_COLUMN,
        DataSetInterface::IMPORT_ID_COLUMN,
        DataSetInterface::UNIQUE_ID_COLUMN,
        DataSetInterface::OVERWRITE_DATE,
        DataSetInterface::ENTRY_DATE_COLUMN
    ];

    private $preparedInsertCount;

    /**
     * ParsedDataImporter constructor.
     * @param EntityManagerInterface $em
     * @param $batchSize
     */
    public function __construct(EntityManagerInterface $em, $batchSize)
    {
        $this->batchSize = $batchSize;
        $this->em = $em;
        $this->conn = $em->getConnection();
    }

    /**
     * @param Collection $collection
     * @param $importId
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return Collection
     * @throws Exception
     */
    public function importParsedDataFromFileToDatabase(Collection $collection, $importId, ConnectedDataSourceInterface $connectedDataSource, DateTime $entryDate)
    {
        $dataSetSynchronizer = new Synchronizer($this->conn, new Comparator());

        //create or get dataSet table
        $table = $dataSetSynchronizer->createEmptyDataSetTable($connectedDataSource->getDataSet());
        $tableName = $table->getName();

        try {
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
                    $columns[] = Synchronizer::getHiddenColumnDay($column);
                    $columns[] = Synchronizer::getHiddenColumnMonth($column);
                    $columns[] = Synchronizer::getHiddenColumnYear($column);
                }
            }

            if ($rows->count() < 1) {
                return true;
            }

            $insert_values = array();
            $columns[DataSetInterface::DATA_SOURCE_ID_COLUMN] = DataSetInterface::DATA_SOURCE_ID_COLUMN;
            $columns[DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN] = DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN;
            $columns[DataSetInterface::IMPORT_ID_COLUMN] = DataSetInterface::IMPORT_ID_COLUMN;
            $columns[DataSetInterface::UNIQUE_ID_COLUMN] = DataSetInterface::UNIQUE_ID_COLUMN;
            $columns[DataSetInterface::ENTRY_DATE_COLUMN] = DataSetInterface::ENTRY_DATE_COLUMN;
            $question_marks = [];
            $uniqueIds = [];
            $this->preparedInsertCount = 0;
            $entryDateStr = $entryDate->format('Y-m-d H:i:s');
            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $this->insertMonthYearAt($dateColumns, $row);
                $uniqueKeys = array_intersect_key($row, $dimensions);
                $uniqueKeys = array_filter($uniqueKeys, function ($value) {
                    return $value !== null;
                });

                $uniqueId = md5(implode(":", $uniqueKeys));
                $row[DataSetInterface::DATA_SOURCE_ID_COLUMN] = $connectedDataSource->getDataSource()->getId();
                $row[DataSetInterface::CONNECTED_DATA_SOURCE_ID_COLUMN] = $connectedDataSource->getId();
                $row[DataSetInterface::IMPORT_ID_COLUMN] = $importId;
                $row[DataSetInterface::UNIQUE_ID_COLUMN] = $uniqueId;
                $row[DataSetInterface::ENTRY_DATE_COLUMN] = $entryDateStr;
                //update
                $uniqueIds[] = $uniqueId;
                $question_marks[] = '(' . $this->placeholders('?', sizeof($row)) . ')';
                $insert_values = array_merge($insert_values, array_values($row));
                $this->preparedInsertCount++;
                if ($this->preparedInsertCount === $this->batchSize) {
                    $this->conn->beginTransaction();
                    $this->executeInsert($tableName, $columns, $question_marks, $insert_values);

                    //commit update and insert
                    $this->conn->commit();
                    $insert_values = [];
                    $question_marks = [];
                    $this->preparedInsertCount = 0;
                }
            }

            if ($this->preparedInsertCount > 0 && is_array($columns) && is_array($question_marks)) {
                $this->conn->beginTransaction();
                $this->executeInsert($tableName, $columns, $question_marks, $insert_values);

                //commit update and insert
                $this->conn->commit();
            }

            return new Collection($columns, $rows);
        } catch (Exception $exception) {
            $this->conn->rollBack();
            throw new Exception($exception->getMessage(), $exception->getCode(), $exception);
        } finally {
            $this->em->clear();
            gc_collect_cycles();
        }
    }

    /**
     * @param array $indexes
     * @param $row
     */
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

            $row[Synchronizer::getHiddenColumnDay($index)] = $day;
            $row[Synchronizer::getHiddenColumnMonth($index)] = $month;
            $row[Synchronizer::getHiddenColumnYear($index)] = $year;

            if (empty($row[$index])) {
                $row[$index] = null;
            }
        }
    }

    /**
     * @param $text
     * @param int $count
     * @param string $separator
     * @return string
     */
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

    /**
     * @param $tableName
     * @param array $columns
     * @param array $question_marks
     * @param array $insert_values
     */
    private function executeInsert($tableName, array $columns, array $question_marks, array $insert_values)
    {
        $insertSql = sprintf("INSERT INTO %s (`%s`) VALUES %s", $tableName, implode("`,`", $columns), implode(',', $question_marks));
        $stmt = $this->conn->prepare($insertSql);
        $stmt->execute($insert_values);
    }

    protected function getEntityManager()
    {
        return $this->em;
    }
}