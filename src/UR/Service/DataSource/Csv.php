<?php

namespace UR\Service\DataSource;

use League\Csv\Reader;

class Csv implements DataSourceInterface
{
    protected $csv;
    protected $headers;
    protected $headerRow = 0;
    protected $dataRow = 1;

    public function __construct($filePath, $delimiter = ',')
    {
        // todo validate $filePath
        $this->csv = Reader::createFromPath($filePath);

        $this->csv->setDelimiter($delimiter);
    }

    public function setDelimiter($delimiter)
    {
        $this->csv->setDelimiter($delimiter);

        return $this;
    }

    public function setHeaderRow($headerRow)
    {
        $this->headerRow = $headerRow;

        return $this;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        $this->setDataRow(0);

        return $this;
    }

    public function setDataRow($dataRow)
    {
        $this->dataRow = $dataRow;

        return $this;
    }

    public function getColumns()
    {
        if (is_array($this->headers)) {
            return $this->headers;
        }

        $match = 0;
        $max = 0;
        $pre_columns = [];

        for ($row = 0; $row <= DataSourceInterface::DETECT_HEADER_ROWS; $row++) {
            $cur_columns = $this->validValue($this->csv->fetchOne($row));

            if (count($cur_columns) > $max) {
                $this->headers = $cur_columns;
                $max = count($this->headers);
                $this->headerRow = $row;
            }

            if ((count($cur_columns) !== count($pre_columns))) {
                $match = 0;
                $pre_columns = $cur_columns;
                continue;
            }

            $match++;
            if ($match === 1) {
                if ($row === 1)
                    $this->dataRow = $row;
                else
                    $this->dataRow = $row - 1;
            }
            if ($match > 10 && count($this->headers) > 0) {
                break;
            }
        }
        // todo make sure there is no file encoding issues for UTF-8, UTF-16, remove special characters

        return $this->headers;
    }

    public function getRows($fromDateFormat)
    {
        $this->csv->setOffset($this->dataRow);

        // todo make sure there is no file encoding issues for UTF-8, UTF-16, remove special characters
        return $this->csv->fetchAll();
    }

    public function validValue(array $arr)
    {
        foreach ($arr as $key => $value) {
            if ($value === null || $value === '') {
                unset($arr[$key]);
            }
        }
        return $arr;
    }

    public function getDataRow()
    {
        return $this->dataRow;
    }
}