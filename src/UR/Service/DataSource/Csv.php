<?php

namespace UR\Service\DataSource;

use League\Csv\AbstractCsv;
use League\Csv\Reader;
use UR\Behaviors\ParserUtilTrait;
use UR\Exception\InvalidArgumentException;

class Csv extends CommonDataSourceFile implements DataSourceInterface
{
    use ParserUtilTrait;

    const DELIMITER_COMMA = ",";
    const DELIMITER_TAB = "\t"; // important: using double quote instead of single quote for special characters!!!

    public static $SUPPORTED_DELIMITERS = [self::DELIMITER_COMMA, self::DELIMITER_TAB];

    /**
     * @var AbstractCsv
     */
    protected $csv;

    /**
     * @var array
     */
    protected $delimiters;

    /**
     * @var array
     */
    protected $headers;
    protected $headerRow = 0;
    protected $dataRow = 0;

    /**
     * Csv constructor.
     * @param string $filePath
     * @param string|array if null, $delimiters default is self::$SUPPORTED_DELIMITERS
     */
    public function __construct($filePath, $delimiters = null)
    {
        // todo validate $filePath
        $this->csv = Reader::createFromPath($filePath, 'r');

        $delimiters = null === $delimiters ? self::$SUPPORTED_DELIMITERS : $delimiters;
        $this->setDelimiters($delimiters);
        $this->detectColumns();
    }

    /**
     * @param array $delimiters
     * @return bool
     */
    public static function isSupportedDelimiters(array $delimiters)
    {
        foreach ($delimiters as $delimiter) {
            if (!in_array($delimiter, self::$SUPPORTED_DELIMITERS)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string|array $delimiters
     * @return $this
     */
    public function setDelimiters($delimiters)
    {
        $delimiters = is_array($delimiters) ? $delimiters : [$delimiters];
        if (!self::isSupportedDelimiters($delimiters)) {
            throw new InvalidArgumentException(sprintf('Not supported delimiters %s for this csv file', implode(',', $delimiters)));
        }

        $this->delimiters = $delimiters;

        return $this;
    }

    /**
     * @param $headerRow
     * @return $this
     */
    public function setHeaderRow($headerRow)
    {
        $this->headerRow = $headerRow;

        return $this;
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        $this->setDataRow(0);

        return $this;
    }

    /**
     * @param $dataRow
     * @return $this
     */
    public function setDataRow($dataRow)
    {
        $this->dataRow = $dataRow;

        return $this;
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

    public function detectColumns()
    {
        if (is_array($this->headers)) {
            return $this->convertEncodingToASCII($this->headers);
        }

        $max = 0;
        $all_rows = [];
        $i = 0;

        // try fetchAll csv with current delimiters
        $validDelimiter = false;
        foreach ($this->delimiters as $delimiter) {
            try {
                $this->csv->setDelimiter($delimiter);
                $this->csv->setLimit(500);
                $this->csv->stripBom(true);
                $all_rows = $this->csv->fetchAll();

                if (is_array($all_rows) && count($all_rows) > 0) {
                    for ($x = 0; $x < self::DETECT_HEADER_ROWS; $x++) {
                        // check the 20 first rows is array and has at least 2 columns
                        $firstRow = $all_rows[$x];
                        if (is_array($firstRow) && count($firstRow) > 1) {
                            // found, so quit the loop
                            $validDelimiter = $delimiter;
                            break;
                        }
                    }
                }

                if ($validDelimiter) {
                    break;
                }
            } catch (\Exception $e) {
                // not support delimiter or other reason, then we try with other delimiters
            }
        }

        // could not parse due to not supported delimiters or other exception
        if (false === $validDelimiter) {
            if (!is_array($this->headers)) {
                $this->csv->setDelimiter(self::DELIMITER_COMMA);
            }
        }

        for ($row = 0; $row < count($all_rows); $row++) {
            $cur_row = $this->removeInvalidColumns($all_rows[$row]);

            if (count($cur_row) < 1) {
                continue;
            }

            $i++;

            if (count($cur_row) > $max) {
                $this->headers = $cur_row;
                $max = count($this->headers);
                $this->headerRow = $row;
                $this->dataRow = $row + 1;
            }

            if ($i >= DataSourceInterface::DETECT_HEADER_ROWS) {
                break;
            }
        }

        if ($this->headers === null) {
            return [];
        }

    }

    /**
     * @inheritdoc
     */
    public function getRows()
    {
        return $this->fetchData();
    }

    /**
     * @param $limit
     * @return array
     */
    public function getLimitedRows($limit = 100)
    {
        if (is_numeric($limit)) {
            $this->csv->setLimit($limit);
        }

        return $this->fetchData();
    }

    /**
     * @inheritdoc
     */
    public function getTotalRows()
    {
        return count($this->getRows());
    }

    private function fetchData()
    {
        $this->csv->setOffset($this->dataRow);
        $this->csv->stripBom(true);
        $allData = $this->csv->fetchAll();
//        $rows = [];
//        foreach ($allData as $item) {
//            // refactor this code
//            $modifiedRow = $this->removeInvalidColumns($item);
//            if (count($modifiedRow) === count($this->headers)) {
//                $rows[] = $modifiedRow;
//            }
//        }
//
//        return $rows;
        // above code is need for csv file has "total" row but not have date column
        // but current not use removeInvalidColumns because this remove entire row if contain an empty value
        // we return this file data
        return $allData;
    }

    /**
     * @return int
     */
    public function getDataRow()
    {
        return $this->dataRow;
    }
}