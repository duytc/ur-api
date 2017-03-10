<?php

namespace UR\Service\DataSource;

use UR\Behaviors\ParserUtilTrait;

class JsonNewFormat extends CommonDataSourceFile implements DataSourceInterface
{
    use ParserUtilTrait;
    protected $rows;
    protected $headers;
    protected $dataRow = 1;

    public function __construct($filePath)
    {
        $str = file_get_contents($filePath, true);
        $json = json_decode($str, true);
        if ($json === null) {
            return;
        }
        $this->headers = $this->getHeaderFromJson($json);
        $this->rows = $this->getRowsFromJson($json);
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
        return $this->rows;
    }

    public function getDataRow()
    {
        return $this->dataRow;
    }

    private function getHeaderFromJson($json)
    {
        return $json['columns'];
    }

    private function getRowsFromJson($json)
    {
        $rows = $json['rows'];
        foreach ($rows as $pos => &$row) {
            $missing_columns = count($this->headers) - count($row);
            if (count($row) == 0){
                unset($rows[$pos]);
            } elseif ($missing_columns > 0) {
                /**
                 * case missing columns
                 */
                for ($loop = 0; $loop < $missing_columns; $loop++) {
                    $row[] = null;
                }
            } elseif ($missing_columns < 0) {
                /**
                 * case excess columns
                 */
                $row = array_slice($row, 0, $missing_columns, true);
            }
        }
        return $rows;
    }
}