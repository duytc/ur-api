<?php

namespace UR\Service\DataSource;

use Box\Spout\Common\Type;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\XLSX\Sheet;
use SplDoublyLinkedList;
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
                if ($this->isTextArray($row)) {
                    $this->headers = $row;
                    $this->headerRow = 1;
                    break;
                }

//                // trim invalid trailing columns, only do this if found header before
//                $currentRow = (is_array($this->headers))
//                    ? $this->removeInvalidTrailingColumns($row)
//                    : $row;
//
//                if (count($currentRow) > $maxColumnsCount) {
//                    // set row with max length as header
//                    $this->headers = $currentRow;
//                    $maxColumnsCount = count($this->headers);
//                    $this->headerRow = $i;
//                }
//
//                if ((count($currentRow) !== count($previousColumns)) && count($currentRow) > 0) {
//                    $match = 0;
//                    $previousColumns = $currentRow;
//                    continue;
//                }
//
//                $match++;
//
//                // set dataRow index due to match
//                if ($match === self::FIRST_MATCH) {
//                    if ($i === self::SECOND_ROW) {
//                        $this->headers = $row;
//                        $this->headerRow = $i;
//                    }
//                }
//
//                if ($match > self::ROW_MATCH) {
//                    break;
//                }
//
//                if ($i > DataSourceInterface::DETECT_HEADER_ROWS) {
//                    break;
//                }
            }

            break;
        }

        $this->dataRow = $this->headerRow + 1;

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
        $rows = new SplDoublyLinkedList();
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

                    foreach ($row as &$value) {
                        $value = $this->normalizeScientificValue($value);
                    }

                    $rows->push($this->removeNonUtf8CharactersForSingleRow($row));
                }

                $curRow++;
            }
        }

        return $rows;
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
        if (!is_numeric($limit)) {
            return $this->getRows();
        }

        $limitedRows = new SplDoublyLinkedList();
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

                    foreach ($row as &$value) {
                        $value = $this->normalizeScientificValue($value);
                    }

                    $limitedRows->push($this->removeNonUtf8CharactersForSingleRow($row));
                }

                $curRow++;

                if (($curRow - $this->dataRow + 1) > $limit) {
                    break;
                }
            }
        }

        return $limitedRows;
    }

    protected function isTextArray(array $array)
    {
        return count(array_filter($array, function($item) {
           return !is_string($item);
        })) < 1;
    }

    /**
     * @inheritdoc
     */
    public function getTotalRows()
    {
        return count($this->getRows());
    }
}