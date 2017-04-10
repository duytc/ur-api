<?php

namespace UR\Service\DataSource;

use Box\Spout\Common\Type;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\XLSX\Sheet;
use Liuggio\ExcelBundle\Factory;
use PHPExcel_Reader_IReader;
use UR\Behaviors\ParserUtilTrait;

class Excel extends CommonDataSourceFile implements DataSourceInterface
{
    use ParserUtilTrait;

    public static $EXCEL_2003_FORMATS = ['Excel5', 'OOCalc', 'Excel2003XML'];
    public static $EXCEL_2007_FORMATS = ['Excel2007'];

    protected $excel;
    protected $sheet;
    protected $headers;
    protected $ogiHeaders;
    protected $rows = [];
    protected $headerRow = 0;
    protected $dataRow = 0;
    protected $inputFileType;
    protected $filePath;
    protected $chunkFile;
    protected $numOfColumns;
    protected $chunkSize;

    /**
     * Excel constructor.
     * @param string $filePath
     * @param Factory $phpExcel
     * @param $chunkSize
     */
    public function __construct($filePath, Factory $phpExcel, $chunkSize)
    {
        $this->chunkSize = $chunkSize;
        $this->filePath = $filePath;
        $this->inputFileType = \PHPExcel_IOFactory::identify($filePath);

        // TODO: breakdown into functions process excel2003 and excel2007
        if (in_array($this->inputFileType, self::$EXCEL_2003_FORMATS)) {
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

        } else if (in_array($this->inputFileType, self::$EXCEL_2007_FORMATS)) {
            $this->excel = ReaderFactory::create(Type::XLSX);
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
     * @param array $fromDateFormats
     * @return array
     */
    public function getRows(array $fromDateFormats)
    {
        if (in_array($this->inputFileType, self::$EXCEL_2003_FORMATS)) {
            $this->rows = [];
            $beginRowsReadRange = $this->dataRow;
            for ($startRow = $this->dataRow; $startRow <= self::MAX_ROW_XLS; $startRow += $this->chunkSize) {
                $objPHPExcel = $this->getPhpExcelObj($this->filePath, $this->chunkSize, $beginRowsReadRange);
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
                            foreach ($fromDateFormats as $field => $format) {
                                if (strcmp($header, $field) === 0) {
                                    $rowData[] = date($format, \PHPExcel_Shared_Date::ExcelToPHP($cell->getValue()));
                                }
                            }

                        } else {
                            $rowData[] = $cell->getValue();
                        }
                    }

                    $this->rows[$row] = $rowData;
                }

                $beginRowsReadRange += $this->chunkSize;
                $objPHPExcel->disconnectWorksheets();
                unset($objPHPExcel, $this->sheet);
                if ($highestRow < $this->chunkSize) {
                    break;
                }
            }

        } else
            if (in_array($this->inputFileType, self::$EXCEL_2007_FORMATS)) {
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
            }

        return $this->rows;
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
     * @param array $array_keys
     * @param array $row
     */
    private function setMissingColumnValueToNull(array $array_keys, array &$row)
    {
        foreach ($array_keys as $array_key) {
            $row[$array_key] = null;
        }
    }
}