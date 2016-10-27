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

        // todo make sure there is no file encoding issues for UTF-8, UTF-16, remove special characters

        return $this->csv->fetchOne($this->headerRow);
    }

    public function getRows()
    {
        $this->csv->setOffset($this->dataRow);

        // todo make sure there is no file encoding issues for UTF-8, UTF-16, remove special characters
        return $this->csv->fetchAll();
    }
}