<?php

namespace UR\Service\DataSource;

use UR\Behaviors\ParserUtilTrait;

class Json extends CommonDataSourceFile implements DataSourceInterface
{
    use ParserUtilTrait;
    protected $rows;
    protected $headers;
    protected $dataRow = 1;

    public function __construct($filePath)
    {
        $str = file_get_contents($filePath, true);
        $this->rows = json_decode($str, true);
        if ($this->rows === null) {
            return;
        }

        $i = 0;
        $header = [];
        for ($rowNum = 0; $rowNum < count($this->rows); $rowNum++) {

            $row = $this->rows[$rowNum];
            $this->rows[$rowNum] = $this->handleNestedColumns($row);
            $cur_row = $this->removeInvalidColumns(array_keys($row));

            foreach ($cur_row as $item) {
                if (!in_array($item, $header)) {
                    $header[] = $item;
                }
            }

            $i++;

            if ($i >= DataSourceInterface::DETECT_JSON_HEADER_ROWS) {
                break;
            }
        }

        $this->headers = $header;
    }

    public function getColumns()
    {
        if (is_array($this->headers)) {
            return $this->convertEncodingToASCII($this->headers);
        }

        return [];
    }

    public function getRows($fromDateFormat)
    {
        //set all missing columns to null
        foreach ($this->rows as $key => &$item) {
            if (count(array_diff_key($item, array_flip($this->headers))) > 0) {
                unset($this->rows[$key]);
                continue;
            }

            if (count($item) === count($this->headers)) {
                continue;
            }

            $missing_columns = array_diff_key(array_flip($this->headers), $item);
            if (count($missing_columns) === 0) {
                continue;
            }

            $row = [];
            foreach ($this->headers as $index => $columnName) {

                if (array_key_exists($columnName, $item)) {
                    $row[$columnName] = $item[$columnName];
                } else {
                    $row[$columnName] = null;
                }
            }

            $item = $row;
        }

        return $this->rows;
    }

    public function getDataRow()
    {
        return $this->dataRow;
    }

    private function handleNestedColumns(&$row)
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
}