<?php

namespace UR\Service\DataSource;

use Box\Spout\Common\Type;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\XLSX\Sheet;
use UR\Behaviors\ParserUtilTrait;

class Excel2007 extends CommonDataSourceFile implements DataSourceInterface
{
    use ParserUtilTrait;

    public static $EXCEL_2007_FORMATS = ['Excel2007'];

    protected $excel;
    protected $sheet;
    protected $headers;
    protected $rows = [];
    protected $headerRow = 0;
    protected $dataRow = 0;
    protected $filePath;
    protected $numOfColumns;
    protected $chunkSize;

    /**
     * Excel constructor.
     * @param string $filePath
     * @param $chunkSize
     */
    public function __construct($filePath, $chunkSize)
    {
        $this->chunkSize = $chunkSize;
        $this->filePath = $filePath;
        $this->excel = ReaderFactory::create(Type::XLSX);
        $this->excel->setShouldFormatDates(true);
        $this->excel->open($filePath);

        foreach ($this->excel->getSheetIterator() as $sheet) {
            $maxColumnsCount = 0;
            $i = 0;
            $previousColumns = [];
            $match = 0;

            /**@var Sheet $sheet */
            foreach ($sheet->getRowIterator() as $rowIndex2 => $row) {
                $i++;

                // trim invalid trailing columns, only do this if found header before
                $currentRow = (is_array($this->headers))
                    ? $this->removeInvalidTrailingColumns($row)
                    : $row;

                if (count($currentRow) > $maxColumnsCount) {
                    // set row with max length as header
                    $this->headers = $currentRow;
                    $maxColumnsCount = count($this->headers);
                    $this->headerRow = $i;
                }

                if ((count($currentRow) !== count($previousColumns)) && count($currentRow) > 0) {
                    $match = 0;
                    $previousColumns = $currentRow;
                    continue;
                }

                $match++;

                // set dataRow index due to match
                if ($match === self::FIRST_MATCH) {
                    if ($i === self::SECOND_ROW) {
                        $this->dataRow = $i;
                    } else {
                        $this->dataRow = $i - 1;
                    }
                }

                if ($match > self::ROW_MATCH) {
                    break;
                }

                if ($i > DataSourceInterface::DETECT_HEADER_ROWS) {
                    break;
                }
            }

            break;
        }

        // finally, set default column name to header for empty values
        if (is_array($this->headers)) {
            $this->headers = $this->setDefaultColumnValueForHeader($this->headers);
        }
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
    public function getRows()
    {
        $this->rows = [];
        $curRow = 1;
        /**
         * @var Sheet $sheet
         */
        foreach ($this->excel->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                if ($curRow >= $this->dataRow) {
                    if (count($row) !== count($this->headers)) {
                        $missingColumns = array_diff_key($this->headers, $row);
                        $this->setMissingColumnValueToNull(array_keys($missingColumns), $row);
                    }

                    $this->rows[$curRow - 1] = $row;
                }

                $curRow++;
            }
        }

        return $this->removeNonUtf8Characters($this->rows);
    }

    public function getDataRow()
    {
        return $this->dataRow;
    }

    /**
     * @param array $array_keys
     * @param array $row
     */
    private function setMissingColumnValueToNull(array $array_keys, array &$row)
    {
        foreach ($array_keys as $array_key) {
            $row[$array_key] = null;
        }
    }

    /**
     * @param $limit
     * @return array
     */
    public function getLimitedRows($limit)
    {
        $limitedRows = [];

        if (!is_numeric($limit)) {
            return $this->removeNonUtf8Characters($this->getRows());
        }

        $curRow = 1;
        /**
         * @var Sheet $sheet
         */
        foreach ($this->excel->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                if ($curRow >= $this->dataRow) {
                    if (count($row) !== count($this->headers)) {
                        $missingColumns = array_diff_key($this->headers, $row);
                        $this->setMissingColumnValueToNull(array_keys($missingColumns), $row);
                    }

                    $limitedRows[$curRow - 1] = $row;
                }

                $curRow++;

                if (($curRow - $this->dataRow + 1) > $limit) {
                    break;
                }
            }
        }

        return $this->removeNonUtf8Characters($limitedRows);
    }

    /**
     * @inheritdoc
     */
    public function getTotalRows()
    {
        return count($this->getRows());
    }
}