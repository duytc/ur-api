<?php

namespace UR\Service\Import;

use League\Csv\Writer;
use UR\Service\DTO\Collection;

class CsvWriter implements CsvWriterInterface
{
    /**
     * @inheritdoc
     */
    public function insertCollection($path, Collection $collection) {
        $csv = $this->initFrom($path);

        /** Write columns as first row */
        $columns = $collection->getColumns();
        $this->insertRow($csv, $columns);

        $rows = $collection->getRows();
        foreach ($rows as $row) {
            $this->insertRow($csv, $row);
        }
    }

    /**
     * @param $path
     * @return Writer
     */
    private function initFrom($path)
    {
        $csv = Writer::createFromPath($path, "w");
        /** We receive bug if use tab (\t) as delimiter, switch to comma separator */
        $csv->setDelimiter(",");
        $csv->setNewline("\r\n"); //use windows line endings for compatibility with some csv libraries
        return $csv;
    }

    /**
     * @param Writer $csv
     * @param $row
     */
    private function insertRow(Writer $csv, $row) {
        $csv->insertOne($row);
    }

    /**
     * @param Writer $csv
     * @param $rows
     */
    private function insertRows(Writer $csv, $rows) {
        $csv->insertAll($rows);
    }
}