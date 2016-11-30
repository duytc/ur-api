<?php

namespace UR\Service\DataSource;

use Box\Spout\Common\Type;
use Box\Spout\Reader\ReaderFactory;
use Liuggio\ExcelBundle\Factory;

class Excel implements DataSourceInterface
{
    protected $excel;
    protected $sheet;
    protected $headers;
    protected $rows;
    protected $headerRow = 0;
    protected $inputFileType;

    public function __construct($filePath, Factory $phpExcel)
    {
        $this->inputFileType = \PHPExcel_IOFactory::identify($filePath);
        if (strcmp($this->inputFileType, 'Excel5') === 0) {
            /**@var Excel $file */
            $this->excel = $phpExcel->createPHPExcelObject($filePath);
            $this->excel->setActiveSheetIndex();
            $this->sheet = $this->excel->getActiveSheet();
            $highestColumn = $this->sheet->getHighestColumn();
            $headings = $this->sheet->rangeToArray('A1:' . $highestColumn . 1,
                NULL,
                TRUE,
                FALSE);
            foreach ($headings as $heading) {
                $this->headers = $heading;
                break;
            }
        } else if (strcmp($this->inputFileType, 'Excel2007') === 0) {
            $this->excel = ReaderFactory::create(Type::XLSX);
            $this->excel->open($filePath);
            foreach ($this->excel->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $this->headers = $row;
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
        if (strcmp($this->inputFileType, 'Excel5') === 0) {
            $highestColumn = $this->sheet->getHighestColumn();
            $columns = range('A', $highestColumn);
            $highestRow = $this->sheet->getHighestRow();
            $this->rows = [];
            $columnsHeaders = array_combine($columns, $this->headers);

            for ($row = 2; $row <= $highestRow; $row++) {
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
//                if ($row === 1) {
//                    $this->headers = $rowData;
//                    continue;
//                }
                $this->rows[$row - 2] = $rowData;
            }

        } else if (strcmp($this->inputFileType, 'Excel2007') === 0) {
            $curRow = 0;
            foreach ($this->excel->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    if ($curRow >= 1)
                        $this->rows[$curRow] = $row;
                    $curRow++;
                }
            }
        }
        return $this->rows;
    }
}