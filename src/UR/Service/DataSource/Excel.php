<?php

namespace UR\Service\DataSource;

use PHPExcel_Reader_IReader;
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
     */
    public function __construct($filePath, $chunkSize)
    {
        $this->chunkSize = $chunkSize;
        $this->filePath = $filePath;
        $objPHPExcel = $this->getPhpExcelObj($filePath, 100, 1);
        $this->sheet = $objPHPExcel->getActiveSheet();
        $this->numOfColumns = $this->sheet->getHighestColumn();

        $maxColumnsCount = 0;
        $previousColumns = [];
        $match = 0;
        $i = 0;

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

            if (count($currentRow) < 1) {
                continue;
            }

            $i++;

            if (count($currentRow) > $maxColumnsCount) {
                $this->headers = $currentRow;
                $this->ogiHeaders = $currentRows[0];
                $maxColumnsCount = count($currentRow);
                $this->headerRow = $rowIndex;
            }

            if ((count($currentRow) !== count($previousColumns)) && count($currentRow) > 0) {
                $match = 0;
                $previousColumns = $currentRow;
                continue;
            }

            $match++;
            if ($match === self::FIRST_MATCH) {
                if ($rowIndex === self::SECOND_ROW)
                    $this->dataRow = $rowIndex;
                else
                    $this->dataRow = $rowIndex - 1;
            }

            if ($match > self::ROW_MATCH && count($this->headers) > 0) {
                break;
            }

            if ($i >= DataSourceInterface::DETECT_HEADER_ROWS) {
                break;
            }

        }
        $objPHPExcel->disconnectWorksheets();
        unset($objPHPExcel, $this->sheet);


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
        $beginRowsReadRange = $this->dataRow;
        for ($startRow = $this->dataRow; $startRow <= self::MAX_ROW_XLS; $startRow += $this->chunkSize) {
            $chunkRows = $this->getChunkRows($beginRowsReadRange, $this->chunkSize);
            if (count($chunkRows) < 1) {
                break;
            }

            $this->rows = array_merge($this->rows, $chunkRows);
        }

        return $this->rows;
    }

    public function getLimitedRows($limit = 100)
    {
        $beginRowsReadRange = $this->dataRow + 2;
        return $this->getChunkRows($beginRowsReadRange, $limit);
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
        $objReader = \PHPExcel_IOFactory::createReaderForFile($filePath);
        $this->chunkFile = new ChunkReadFilter();
        $objReader->setReadFilter($this->chunkFile);
        $objReader->setReadDataOnly(true);
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

    private function getChunkRows(&$beginRowsReadRange, $chunkSize)
    {
        $chunkRows = [];
        $objPHPExcel = $this->getPhpExcelObj($this->filePath, $chunkSize, $beginRowsReadRange);
        $this->sheet = $objPHPExcel->getActiveSheet();

        $columns = range('A', $this->numOfColumns);
        $highestRow = $this->sheet->getHighestDataRow();
        $columnsHeaders = array_combine($columns, $this->ogiHeaders);
        $columnsHeaders = array_filter($columnsHeaders, function ($value) {
            return (!is_null($value) && !empty($value)) || $value === '0';
        });

        for ($row = $beginRowsReadRange; $row <= $highestRow; $row++) {
            $rowData = [];
            foreach ($columnsHeaders as $column => $header) {
                $cell = $this->sheet->getCell($column . $row);

                if (\PHPExcel_Shared_Date::isDateTime($cell)) {
                    foreach ($this->fromDateFormats as $field => $format) {
                        if (strcmp($header, $field) === 0) {
                            $rowData[] = date($format, \PHPExcel_Shared_Date::ExcelToPHP($cell->getValue()));
                        }
                    }

                } else {
                    $rowData[] = $cell->getValue();
                }
            }

            $chunkRows[$row] = $rowData;
        }

        $beginRowsReadRange += $chunkSize;
        $objPHPExcel->disconnectWorksheets();
        unset($objPHPExcel, $this->sheet);
        return $chunkRows;
    }
}