<?php

namespace UR\Service\DataSource;

use League\Csv\AbstractCsv;
use League\Csv\Reader;
use UR\Exception\InvalidArgumentException;

class Csv implements DataSourceInterface
{
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
        $this->csv = Reader::createFromPath($filePath);

        $delimiters = null === $delimiters ? self::$SUPPORTED_DELIMITERS : $delimiters;
        $this->setDelimiters($delimiters);
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
        $all_columns = [];
        $i = 0;

        // try fetchAll csv with current delimiters
        $validDelimiter = false;
        foreach ($this->delimiters as $delimiter) {
            try {
                $this->csv->setDelimiter($delimiter);
                $all_columns = $this->csv->fetchAll();

                if (is_array($all_columns) && count($all_columns) > 0) {
                    // check the first row is array and has at least 2 columns
                    $firstRow = $all_columns[0];
                    if (is_array($firstRow) && count($firstRow) > 1) {
                        // found, so quit the loop
                        $validDelimiter = $delimiter;
                        break;
                    }
                }
            } catch (\Exception $e) {
                // not support delimiter or other reason, then we try with other delimiters
            }
        }

        // could not parse due to not supported delimiters or other exception
        if (false === $validDelimiter) {
            return $this->headers; // TODO: why?
        }

        for ($row = 0; $row <= count($all_columns); $row++) {
            // TODO: why not use fetched $all_columns instead of fetch each row
            $cur_row = $this->validValue($this->csv->fetchOne($row));

            if (count($cur_row) < 1) {
                continue;
            }

            $i++;

            if (count($cur_row) > $max) {
                $this->headers = $cur_row;
                $max = count($this->headers);
                $this->headerRow = $row;
            }

            if ((count($cur_row) !== count($pre_columns))) {
                $match = 0;
                $pre_columns = $cur_row;
                continue;
            }

            $match++;
            if ($match === 1) {
                if ($row === 2)
                    $this->dataRow = $row - 1;
                else
                    $this->dataRow = $row;
            }

            if ($match > 10 && count($this->headers) > 0) {
                break;
            }

            if ($i >= DataSourceInterface::DETECT_HEADER_ROWS) {
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