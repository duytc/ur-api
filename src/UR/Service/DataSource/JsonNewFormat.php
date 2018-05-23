<?php

namespace UR\Service\DataSource;

use UR\Behaviors\ParserUtilTrait;

class JsonNewFormat extends CommonDataSourceFile implements DataSourceInterface
{
    use ParserUtilTrait;
    protected $rows = [];
    protected $headers = [];
    protected $dataRow = 1;

    /**
     * JsonNewFormat constructor.
     * @param string $filePath
     */
    public function __construct($filePath)
    {
        $str = file_get_contents($filePath, true);
        $result = json_decode($str, true);
        if (!is_array($result)) {
            return;
        }

        $this->headers = $this->getHeaderFromJson($result);
        $this->rows = $this->getRowsFromJson($result);
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        if (is_array($this->headers)) {
            return $this->convertEncodingToASCII($this->headers);
        }

        return [];
    }

    /**
     * @inheritdoc
     */
    public function getRows($sheets = [])
    {
        return $this->removeNonUtf8Characters($this->rows);
    }

    /**
     * @return int
     */
    public function getDataRow()
    {
        return $this->dataRow;
    }

    /**
     * @param array $json
     * @return array
     */
    private function getHeaderFromJson(array $json)
    {
        return $json['columns'];
    }

    /**
     * @param array $json
     * @return array
     */
    private function getRowsFromJson(array $json)
    {
        $rows = $json['rows'];
        foreach ($rows as $pos => &$row) {
            $missing_columns = count($this->headers) - count($row);
            if (count($row) == 0) {
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

    /**
     * @inheritdoc
     */
    public function getLimitedRows($limit, $sheets = [])
    {
        if (is_numeric($limit)) {
            return $this->removeNonUtf8Characters(array_slice($this->rows, 0, $limit));
        }

        return $this->removeNonUtf8Characters($this->rows);
    }

    /**
     * @inheritdoc
     */
    public function getTotalRows($sheets = [])
    {
        return count($this->getRows($sheets));
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }
}