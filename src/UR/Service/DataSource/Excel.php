<?php

namespace UR\Service\DataSource;

use Box\Spout\Common\Type;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\XLSX\Sheet;
use Liuggio\ExcelBundle\Factory;
use UR\Behaviors\ParserUtilTrait;

class Excel implements DataSourceInterface
{
    use ParserUtilTrait;
    private $excel_2003_formats = ['Excel5', 'OOCalc', 'Excel2003XML'];
    private $excel_2007_formats = ['Excel2007'];

    protected $excel;
    protected $sheet;
    protected $headers;
    protected $ogiHeaders;
    protected $rows = [];
    protected $headerRow = 0;
    protected $dataRow = 0;
    protected $inputFileType;

    public function __construct($filePath, Factory $phpExcel)
    {
        $this->inputFileType = \PHPExcel_IOFactory::identify($filePath);
        if (in_array($this->inputFileType, $this->excel_2003_formats)) {
            $this->excel = $phpExcel->createPHPExcelObject($filePath);
            $this->excel->setActiveSheetIndex();
            $this->sheet = $this->excel->getActiveSheet();
            $max = 0;
            $pre_columns = [];
            $match = 0;
            $i = 0;
            for ($row = 1; $row <= $this->sheet->getHighestDataRow(); $row++) {
                $highestDataColumn = $this->sheet->getHighestDataColumn($row);
                $cur_rows = $this->sheet->rangeToArray('A' . $row . ':' . $highestDataColumn . $row,
                    NULL,
                    TRUE,
                    FALSE);

                $cur_row = array_filter($cur_rows[0], function ($value) {
                    if (is_numeric($value)) {
                        return true;
                    }
                    return (!is_null($value) && !empty($value));
                });

                if (count($cur_row) < 1) {
                    continue;
                }

                $i++;

                if (count($cur_row) > $max) {
                    $this->headers = $cur_row;
                    $this->ogiHeaders = $cur_rows[0];
                    $max = count($cur_row);
                    $this->headerRow = $row;
                }

                if ((count($cur_row) !== count($pre_columns)) && count($cur_row) > 0) {
                    $match = 0;
                    $pre_columns = $cur_row;
                    continue;
                }

                $match++;
                if ($match === self::FIRST_MATCH) {
                    if ($row === self::SECOND_ROW)
                        $this->dataRow = $row;
                    else
                        $this->dataRow = $row - 1;
                }

                if ($match > self::ROW_MATCH && count($this->headers) > 0) {
                    break;
                }

                if ($i >= DataSourceInterface::DETECT_HEADER_ROWS)
                    break;
            }
        } else if (in_array($this->inputFileType, $this->excel_2007_formats)) {
            $this->excel = ReaderFactory::create(Type::XLSX);
            $this->excel->open($filePath);
            // TODO: check setShouldFormatDates() is what???
            $this->excel->setShouldFormatDates(false);
            foreach ($this->excel->getSheetIterator() as $sheet) {
                $max = 0;
                $i = 0;
                $pre_columns = [];
                $match = 0;
                /**@var Sheet $sheet */
                foreach ($sheet->getRowIterator() as $row) {
                    $i++;
                    $cur_row = $this->validValue($row);

                    if (count($cur_row) > $max) {
                        $this->headers = $cur_row;
                        $max = count($this->headers);
                        $this->headerRow = $i;
                    }

                    if ((count($cur_row) !== count($pre_columns)) && count($cur_row) > 0) {
                        $match = 0;
                        $pre_columns = $cur_row;
                        continue;
                    }

                    $match++;
                    if ($match === self::FIRST_MATCH) {
                        if ($i === self::SECOND_ROW)
                            $this->dataRow = $i;
                        else
                            $this->dataRow = $i - 1;
                    }
                    if ($match > self::ROW_MATCH) {
                        break;
                    }

                    if ($i > DataSourceInterface::DETECT_HEADER_ROWS)
                        break;
                }
                break;
            }
        }
    }

    public function getColumns()
    {
        if (is_array($this->headers)) {
            return $this->convertEncodingToASCII($this->headers);
        }

        return [];
    }

    public function getRows($fromDateFormats)
    {
        if (in_array($this->inputFileType, $this->excel_2003_formats)) {
            $highestColumn = $this->sheet->getHighestDataColumn();
            $columns = range('A', $highestColumn);
            $highestRow = $this->sheet->getHighestDataRow();
            $this->rows = [];
            $columnsHeaders = array_combine($columns, $this->ogiHeaders);
            $columnsHeaders = array_filter($columnsHeaders, function ($value) {
                return (!is_null($value) && !empty($value)) || $value === '0';
            });

            for ($row = $this->dataRow; $row <= $highestRow; $row++) {
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
                        $rowData[] = $cell->getFormattedValue();
                    }
                }

                $this->rows[$row - 2] = $rowData;
            }

        } else
            if (in_array($this->inputFileType, $this->excel_2007_formats)) {
                $curRow = 1;
                foreach ($this->excel->getSheetIterator() as $sheet) {
                    foreach ($sheet->getRowIterator() as $row) {
                        if ($curRow >= $this->dataRow)
                            $this->rows[$curRow - 1] = $row;
                        $curRow++;
                    }
                }
            }
        return $this->rows;
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