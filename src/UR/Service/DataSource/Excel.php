<?php

namespace UR\Service\DataSource;

use Box\Spout\Common\Type;
use Box\Spout\Reader\ReaderFactory;
use Box\Spout\Reader\XLSX\Sheet;
use Liuggio\ExcelBundle\Factory;

class Excel implements DataSourceInterface
{
    protected $excel;
    protected $sheet;
    protected $headers;
    protected $rows;
    protected $headerRow = 0;
    protected $dataRow = 0;
    protected $inputFileType;

    public function __construct($filePath, Factory $phpExcel)
    {
        $this->inputFileType = \PHPExcel_IOFactory::identify($filePath);
        if (in_array($this->inputFileType, ['Excel5', 'OOCalc', 'Excel2003XML'])) {
            $this->excel = $phpExcel->createPHPExcelObject($filePath);
            $this->excel->setActiveSheetIndex();
            $this->sheet = $this->excel->getActiveSheet();
            $max = 0;
            $pre_columns = [];
            $match = 0;
            for ($row = 1; $row <= DataSourceInterface::DETECT_HEADER_ROWS; $row++) {
                $highestDataColumn = $this->sheet->getHighestDataColumn($row);
                $cur_rows = $this->sheet->rangeToArray('A' . $row . ':' . $highestDataColumn . $row,
                    NULL,
                    TRUE,
                    FALSE);

                if (!is_array($cur_rows[0])) {
                    $this->headers = [];
                }

                $cur_row = array_filter($cur_rows[0], function ($value) {
                    return (!is_null($value) && !empty($value)) || $value === '0';
                });

                if (count($cur_row) > $max) {
                    $this->headers = $cur_row;
                    $max = count($cur_row);
                    $this->headerRow = $row;
                }

                if ((count($cur_row) !== count($pre_columns)) && count($cur_row) > 0) {
                    $match = 0;
                    $pre_columns = $cur_row;
                    continue;
                }

                $match++;
                if ($match === 1) {
                    if ($row === 2)
                        $this->dataRow = $row;
                    else
                        $this->dataRow = $row - 1;
                }

                if ($match > 10) {
                    break;
                }

                if ($row > DataSourceInterface::DETECT_HEADER_ROWS)
                    break;
            }
        } else if ($this->inputFileType === 'Excel2007') {
            $this->excel = ReaderFactory::create(Type::XLSX);
            $this->excel->open($filePath);
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

                    if (count($row) > $max) {
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
                    if ($match === 1) {
                        if ($i === 2)
                            $this->dataRow = $i;
                        else
                            $this->dataRow = $i - 1;
                    }
                    if ($match > 10) {
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
            return $this->headers;
        }
        // todo
        return [];
    }

    public function getRows($fromDateFormats)
    {
        if (in_array($this->inputFileType, ['Excel5', 'OOCalc', 'Excel2003XML'])) {
            $highestColumn = $this->sheet->getHighestColumn();
            $columns = range('A', $highestColumn);
            $highestRow = $this->sheet->getHighestRow();
            $this->rows = [];
            $columnsHeaders = array_combine($columns, $this->headers);

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
            if (strcmp($this->inputFileType, 'Excel2007') === 0) {
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
}