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

    public function getRows()
    {
        if (strcmp($this->inputFileType, 'Excel5') === 0) {
            $highestColumn = $this->sheet->getHighestColumn();
            $columns = range('A', $highestColumn);
            $highestRow = $this->sheet->getHighestRow();
            $this->rows = [];
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                foreach ($columns as $column) {
                    $cell = $this->sheet->getCell($column . $row);
                    if (\PHPExcel_Shared_Date::isDateTime($cell)) {
                        $rowData[] = date('d/m/Y', \PHPExcel_Shared_Date::ExcelToPHP($cell->getValue()));
                    } else {
                        $rowData[] = $cell->getFormattedValue();
                    }
                }
                if ($row === 1) {
                    $this->headers = $rowData;
                    continue;
                }
                $this->rows[$row - 2] = $rowData;
            }

        } else if (strcmp($this->inputFileType, 'Excel2007') === 0) {
            $currow = 0;
            foreach ($this->excel->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    if ($currow >= 1)
                        $this->rows[$currow] = $row;
                    $currow++;
                }
            }
        }
        return $this->rows;
    }
}