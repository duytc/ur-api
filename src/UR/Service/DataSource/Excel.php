<?php

namespace UR\Service\DataSource;

use PHPExcel_IOFactory;
use PHPExcel_Reader_IReader;
use PHPExcel_Shared_Date;
use PHPExcel_Worksheet;
use SplDoublyLinkedList;
use UR\Behaviors\ParserUtilTrait;

class Excel extends CommonDataSourceFile implements DataSourceInterface
{
    use ParserUtilTrait;

    public static $EXCEL_2003_FORMATS = ['Excel5', 'OOCalc', 'Excel2003XML'];

    protected $excel;
    protected $sheet;
    protected $headers;
    protected $ogiHeaders;
    protected $rows = [];
    protected $headerRow = 0;
    protected $dataRow = 0;
    protected $filePath;
    protected $chunkFile;
    protected $numOfColumns;
    protected $chunkSize;
    protected $fromDateFormats = [];

    /**
     * Excel constructor.
     * @param string $filePath
     * @param $chunkSize
     * @param array $sheets
     */
    public function __construct($filePath, $chunkSize, $sheets = [])
    {
        $this->chunkSize = $chunkSize;
        $this->filePath = $filePath;
        $this->detectColumns($sheets);
        $DateFormats = [
            'DateTime' => 'Y-m-d H:i:s',
            'dateTime' => 'Y-m-d H:i:s',
            'datetime' => 'Y-m-d H:i:s',
            'Datetime' => 'Y-m-d H:i:s',
            'date' => 'Y-m-d',
            'Date' => 'Y-m-d'
        ];
        $this->fromDateFormats = $DateFormats;
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


    public function detectColumns($sheets = [])
    {
        $objPHPExcel = $this->getPhpExcelObj($this->filePath, 100, 1);
        foreach ($objPHPExcel->getAllSheets() as $sheet) {
            if (is_array($sheets) && !empty($sheets) && !in_array($sheet->getTitle(), $sheets)) {
                continue;
            }
            $this->sheet = $sheet;
            break;
        }
        if (!$this->sheet instanceof PHPExcel_Worksheet) {
            $this->sheet = $objPHPExcel->getActiveSheet();
        }
        $this->numOfColumns = $this->sheet->getHighestColumn();

        $i = 0;
        $maxColumns = 0;

        for ($rowIndex = 1, $highestDataRow = $this->sheet->getHighestDataRow(); $rowIndex <= $highestDataRow; $rowIndex++) {
            $highestDataColumnIndex = $this->sheet->getHighestDataColumn($rowIndex);
            $currentRows = $this->sheet->rangeToArray(
                'A' . $rowIndex . ':' . $highestDataColumnIndex . $rowIndex,
                $nullValue = null,
                $calculateFormulas = true,
                $formatData = false, $returnCellRef = false
            );

            $currentRow = array_filter($currentRows[0], function ($value) {
                if (is_numeric($value)) {
                    return true;
                }

                return (!is_null($value) && !empty($value));
            });

            $currentRow = $this->removeInvalidColumns($currentRow);

            if (count($currentRow) < 1) {
                continue;
            }

            $i++;

            if ($this->isTextArray($currentRow) && !$this->isEmptyArray($currentRow) && count($currentRow) > $maxColumns) {
                $this->headers = $currentRow;
                $this->ogiHeaders = $currentRow;
                $maxColumns = count($this->headers);
                $this->headerRow = $rowIndex;
            }

            if ($i >= DataSourceInterface::DETECT_HEADER_ROWS) {
                break;
            }

        }

        $this->dataRow = $this->headerRow + 1;

        $objPHPExcel->disconnectWorksheets();
        unset($objPHPExcel, $this->sheet);


        // finally, set default column name to header for empty values
        if (is_array($this->headers)) {
            $this->headers = $this->setDefaultColumnValueForHeader($this->headers);
        }
    }

    /**
     * @inheritdoc
     */
    public function getRows($sheets = [])
    {
        $rows = new SplDoublyLinkedList();
        $beginRowsReadRange = $this->dataRow;
        for ($startRow = $this->dataRow; $startRow <= self::MAX_ROW_XLS; $startRow += $this->chunkSize) {
            $chunkRows = $this->getChunkRows($beginRowsReadRange, $this->chunkSize, $sheets);
            if (count($chunkRows) < 1) {
                break;
            }

            foreach ($chunkRows as $chunkRow) {
                $rows->push($chunkRow);
            }
        }

        return $rows;
    }

    /**
     * @inheritdoc
     */
    public function getLimitedRows($limit = 100, $sheets = [])
    {
        if (!is_numeric($limit)) {
            return $this->getRows($sheets);
        }
        $rows = new SplDoublyLinkedList();
        $beginRowsReadRange = $this->dataRow;
        $chunkRows = $this->getChunkRows($beginRowsReadRange, $limit, $sheets);
        foreach ($chunkRows as $chunkRow) {
            $rows->push($chunkRow);
        }

        return $rows;
    }

    /**
     * @inheritdoc
     */
    public function getTotalRows($sheets = [])
    {
        return count($this->getRows($sheets));
    }

    public function getDataRow()
    {
        return $this->dataRow;
    }

    /**
     * @param string $filePath
     * @param int $chunkSize
     * @param int $startRow
     * @return \PHPExcel
     */
    private function getPhpExcelObj($filePath, $chunkSize, $startRow)
    {
        /**
         * @var PHPExcel_Reader_IReader $objReader
         */
        $objReader = PHPExcel_IOFactory::createReaderForFile($filePath);
        $this->chunkFile = new ChunkReadFilter();
        //$objReader->setReadFilter($this->chunkFile);
        //$objReader->setReadDataOnly(true);
        //READ 100 FIRST ROWS TO DETECT HEADER
        $this->chunkFile->setRows($startRow, $chunkSize);
        $objPHPExcel = $objReader->load($filePath);
        return $objPHPExcel;
    }

    /**
     * @param array $fromDateFormats
     */
    public function setFromDateFormats(array $fromDateFormats)
    {
        $this->fromDateFormats = $fromDateFormats;
    }

    private function getChunkRows(&$beginRowsReadRange, $chunkSize, $sheets = [])
    {
        $chunkRows = [];
        $objPHPExcel = $this->getPhpExcelObj($this->filePath, $chunkSize, $beginRowsReadRange);

        $columns = [];
        foreach ($this->excelColumnRange('A', $this->numOfColumns) as $value) {
            $columns[] = $value;
        }
        $columnsHeaders = [];
        try {
            $this->ogiHeaders = is_array($this->ogiHeaders) ? $this->ogiHeaders : [$this->ogiHeaders];
            $columnsHeaders = array_combine($columns, $this->ogiHeaders);
        } catch (\Exception $e) {

        }

        $columnsHeaders = is_array($columnsHeaders) ? $columnsHeaders : [$columnsHeaders];
        $columnsHeaders = array_filter($columnsHeaders, function ($value) {
            return (!is_null($value) && !empty($value)) || $value === '0';
        });
        $i = $beginRowsReadRange;
        $beginRowsRead = $beginRowsReadRange;

        foreach ($objPHPExcel->getAllSheets() as $sheet) {
            if(!$sheet instanceof PHPExcel_Worksheet){
                continue;
            }
            if (is_array($sheets) && !empty($sheets) && !in_array($sheet->getTitle(), $sheets)) {
                continue;
            }
            $highestRow = $sheet->getHighestDataRow();
            if ($i > $beginRowsReadRange) {
                $beginRowsRead = 1;
            }

            for ($row = $beginRowsRead; $row <= $highestRow; $row++) {
                $rowData = [];
                $columnsHeaders = is_array($columnsHeaders) ? $columnsHeaders : [$columnsHeaders];
                foreach ($columnsHeaders as $column => $header) {
                    $cell = $sheet->getCell($column . $row);

                    if (PHPExcel_Shared_Date::isDateTime($cell)) {
                        foreach ($this->fromDateFormats as $field => $format) {
                            if ($header === $field) {
                                $rowData[] = date($format, PHPExcel_Shared_Date::ExcelToPHP($this->normalizeScientificValue($cell->getValue())));
                            }
                        }

                    } else {
                        $rowData[] = $this->normalizeScientificValue($cell->getValue());
                    }
                }
                if($rowData == $this->getHeaders()){
                    continue;
                }
                $chunkRows[$i] = $this->removeNonUtf8CharactersForSingleRow($rowData);
                $i++;
            }
        }
        $beginRowsReadRange += $chunkSize;
        $objPHPExcel->disconnectWorksheets();
        unset($objPHPExcel);

        return $chunkRows;
    }

    private function excelColumnRange($lower, $upper)
    {
        ++$upper;
        for ($i = $lower; $i !== $upper; ++$i) {
            yield $i;
        }
    }

    private function countRows(&$startRow, $chunkSize)
    {
        $objPHPExcel = $this->getPhpExcelObj($this->filePath, $chunkSize, $startRow);
        $highestRow = 0;
        foreach ($objPHPExcel->getAllSheets() as $sheet) {
            $highestRow = +$sheet->getHighestDataRow() - $startRow + 1;
        }
        $objPHPExcel->disconnectWorksheets();
        $startRow += $chunkSize;
        unset($objPHPExcel);
        return $highestRow;
    }

    /**
     * Get header row of excel file
     * @return array
     */
    public function getHeaderRow()
    {
        return $this->headers;
    }

    /**
     * Get body rows of excel file
     */
    public function getBodyRows()
    {
        $rowValues = [];
        $dll = $this->getRows();
        $dll->rewind();

        while ($dll->valid()) {
            $rowValues[] = $dll->current();
            $dll->next();
        }

        return $rowValues;
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }
}