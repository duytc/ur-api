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
        for ($row = 0; $row < count($this->rows); $row++) {
            $cur_row = $this->removeInvalidColumns(array_keys($this->rows[$row]));

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
            foreach ($missing_columns as $name => $index) {
                $row = [];
                $i = 0;
                foreach ($item as $k => $v) {
                    if ($index === 0) {
                        $row[$name] = null;
                    }

                    $row[$k] = $v;
                    $i++;
                    if ($i === $index) {
                        $row[$name] = null;
                    }
                }

                $item = $row;
            }
        }

        return $this->rows;
    }

    public function getDataRow()
    {
        return $this->dataRow;
    }
}