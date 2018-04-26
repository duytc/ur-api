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

        $headerLength = $this->getHeaderLength();
        foreach ($this->excel->getSheetIterator() as $sheet) {
            /**@var Sheet $sheet */
            foreach ($sheet->getRowIterator() as $rowIndex2 => $row) {
                if ($this->isTextArray($row) && !$this->isEmptyArray($row) && count($this->trimArray($row)) == $headerLength) {
                    $this->headers = $row;
                    $this->headerRow = $rowIndex2;
                    break;
                }
            }

            break;
        }

        $this->dataRow = $this->headerRow + 1;

        // finally, set default column name to header for empty values
        if (is_array($this->headers)) {
            $this->headers = $this->setDefaultColumnValueForHeader($this->headers);
        }
    }

    public function getHeaderLength()
    {
        $rowLength = [];
        foreach ($this->excel->getSheetIterator() as $sheet) {
            /**@var Sheet $sheet */
            foreach ($sheet->getRowIterator() as $rowIndex2 => $row) {
                if ($rowIndex2 > 200) {
                    break;
                }
                if (is_array($row)) {
                    $row = $this->trimArray($row);
                    if (empty($row)) {
                        continue;
                    }
                    if (isset($rowLength[count($row)])) {
                        $rowLength[count($row)] += 1;
                    } else $rowLength[count($row)] = 1;
                }
            }

            break;
        }

        $maxLength = -1;
        foreach ($rowLength as $length => $frequency) {
            if ($maxLength == -1) {
                $maxLength = $length;
                continue;
            }

            if ($frequency > 1 && $maxLength < $length) {
                $maxLength = $length;
            }
        }

        return $maxLength;
    }

    public function trimArray(array $data)
    {
        //Ignore empty cell (null, ""). Do not ignore zero values as 0, 0%
        return array_filter($data, function ($cell) {
            return !is_null($cell) && $cell !== "";
        });
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
                    if ($this->isEmptyRow($row)) {
                        continue;
                    }

                    if (count($row) !== count($this->headers)) {
                        if (!is_array($this->headers)) {
                            $this->headers = [];
                        }
                        $missingColumns = array_diff_key($this->headers, $row);

                        if (!is_array($missingColumns)) {
                            $missingColumns = [];
                        }
                        $this->setMissingColumnValueToNull(array_keys($missingColumns), $row);
                    }

                    foreach ($row as &$value) {
                        $value = $this->normalizeScientificValue($value);
                    }

                    $this->removeEmptyData($row);
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
     * @param array $row_data
     * remove all empty items from row
     */
    private function removeEmptyData(array &$row)
    {
        if(count($row) > count($this->headers))
            foreach ($row as $index => $value) {
                $tmpIndex = $index + 1;
                if(empty($value) && $tmpIndex > count($this->headers))
                    unset($row[$index]);
            }
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
                    if ($this->isEmptyRow($row)) {
                        continue;
                    }
                    if (count($row) !== count($this->headers)) {
                        if (!is_array($this->headers)) {
                            $this->headers = [];
                        }
                        $missingColumns = array_diff_key($this->headers, $row);
                        if (!is_array($missingColumns)) {
                            $missingColumns = [];
                        }
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

    /**
     * @inheritdoc
     */
    public function getTotalRows()
    {
        return count($this->getRows());
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }
}