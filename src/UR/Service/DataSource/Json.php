<?php

namespace UR\Service\DataSource;

use \SplDoublyLinkedList;
use JsonStreamingParser\Listener\InMemoryListener;
use JsonStreamingParser\Parser;
use UR\Behaviors\ParserUtilTrait;
use UR\Service\ArrayUtilTrait;

class Json extends CommonDataSourceFile implements DataSourceInterface
{
    use ParserUtilTrait;
    use ArrayUtilTrait;

    public $headers;
    public $dataRow = 1;
    public $listener;

    /**
     * Json constructor.
     * @param $filePath
     * @throws \Exception
     */
    public function __construct($filePath)
    {
        $stream = fopen($filePath, 'r');
        $this->listener = new InMemoryListener();
        try {
            $parser = new Parser($stream, $this->listener);
            $parser->parse();
            fclose($stream);
        } catch (\Exception $e) {
            fclose($stream);
            throw new \Exception(sprintf('Does not support this JSON format'));
        }

        $data = $this->listener->getJson();
        if (array_key_exists('columns', $data) && array_key_exists('rows', $data)) {
            $this->headers = $data['columns'];
            return;
        }

        $header = [];
        $i = 0;
        foreach ($data as $row) {
            $row = $this->handleNestedColumns($row);
            $row = $this->removeInvalidColumns($row);

            if (!$this->isAssoc($row)) {
                $header = $row;
                break;
            }

            foreach ($row as $key => $item) {
                if (!in_array($key, $header)) {
                    $header[] = $key;
                }
            }

            $i++;

            if ($i >= DataSourceInterface::DETECT_JSON_HEADER_ROWS) {
                break;
            }
        }

        unset($data, $row);
        $this->headers = $header;
    }


    /**
     * @return array
     */
    public function getColumns()
    {
        if (count($this->headers) > 0) {
            return $this->convertEncodingToASCII($this->headers);
        }

        return [];
    }

    /**
     * @inheritdoc
     */
    public function getRows()
    {
        $data = $this->listener->getJson();
        if (array_key_exists('columns', $data) && array_key_exists('rows', $data)) {
            return $this->getRowsFromJsonObject($data);
        }

        $newRows = new SplDoublyLinkedList();
        //set all missing columns to null
        foreach ($data as $key => &$item) {
            if (count(array_diff_key($item, array_flip($this->headers))) > 0) {
                continue;
            }

            if (count($item) === count($this->headers)) {
                $newRows->push($this->removeNonUtf8CharactersForSingleRow($item));
                continue;
            }

            $missing_columns = array_diff_key(array_flip($this->headers), $item);
            if (count($missing_columns) === 0) {
                $newRows->push($this->removeNonUtf8CharactersForSingleRow($item));
                continue;
            }

            foreach ($this->headers as $index => $columnName) {
                if (!array_key_exists($columnName, $item)) {
                    $item[$columnName] = null;
                }
            }

            $newRows->push($this->removeNonUtf8CharactersForSingleRow($item));
        }

        unset($item, $data);
        return $newRows;
    }

    /**
     * @param $data
     * @param $limit
     * @return SplDoublyLinkedList
     */
    public function getRowsFromJsonObject($data, $limit = null)
    {
        $rows = $data['rows'];
        $newRows = new SplDoublyLinkedList();
        $count = 0;
        foreach ($rows as $pos => &$row) {
            if (count($row) == 0) {
                continue;
            }

            $missing_columns = count($this->headers) - count($row);

            if ($missing_columns > 0) {
                 //fill missing columns with null
                for ($loop = 0; $loop < $missing_columns; $loop++) {
                    $row[] = null;
                }

                $newRows->push($row);
                $count++;
                continue;
            }

            if ($missing_columns < 0) {
                $row = array_slice($row, 0, $missing_columns, true);
                $newRows->push($row);
                $count++;
                continue;
            }

            $newRows->push($row);
            $count++;

            if (is_numeric($limit) && $count >= $limit) {
                break;
            }
        }

        unset($rows, $row);
        return $newRows;
    }

    /**
     * @return int
     */
    public function getDataRow()
    {
        return $this->dataRow;
    }

    /**
     * @param array $row
     * @return array
     */
    private function handleNestedColumns(array &$row)
    {
        foreach ($row as $column => &$value) {
            if (!is_array($value)) {
                continue;
            }

            foreach ($value as $childColumn => $childValue) {
                $newColumn = $column . "." . $childColumn;
                $row[$newColumn] = $childValue;
            }

            unset($row[$column]);
        }

        return $row;
    }

    /**
     * @param $limit
     * @return array
     */
    public function getLimitedRows($limit)
    {
        if (!is_numeric($limit)) {
            return $this->getRows();
        }

        $data = $this->listener->getJson();
        if (array_key_exists('columns', $data) && array_key_exists('rows', $data)) {
            return $this->getRowsFromJsonObject($data, $limit);
        }

        $newRows = new SplDoublyLinkedList();
        //set all missing columns to null
        $count = 1;
        foreach ($data as $key => &$item) {
            if (count(array_diff_key($item, array_flip($this->headers))) > 0) {
                continue;
            }

            if (count($item) === count($this->headers)) {
                $newRows->push($this->removeNonUtf8CharactersForSingleRow($item));
                continue;
            }

            $missing_columns = array_diff_key(array_flip($this->headers), $item);
            if (count($missing_columns) === 0) {
                $newRows->push($this->removeNonUtf8CharactersForSingleRow($item));
                continue;
            }

            foreach ($this->headers as $index => $columnName) {
                if (!array_key_exists($columnName, $item)) {
                    $item[$columnName] = null;
                }
            }

            $newRows->push($this->removeNonUtf8CharactersForSingleRow($item));

            $count++;
            if ($count >= $limit) {
                break;
            }
        }

        unset($item, $data);
        return $newRows;
    }

    /**
     * @inheritdoc
     */
    public function getTotalRows()
    {
        return count($this->getRows());
    }
}