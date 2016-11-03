<?php

namespace UR\Service\DataSource;

use Liuggio\ExcelBundle\Factory;

class Excel implements DataSourceInterface
{
    protected $excel;
    protected $sheet;
    protected $headers;
    protected $rows;
    protected $headerRow = 0;

    public function __construct($filePath, Factory $phpExcel)
    {
        $this->excel = $phpExcel->createPHPExcelObject($filePath);
        $this->excel->setActiveSheetIndex();
        $this->sheet = $this->excel->getActiveSheet();
        $highestRow = $this->sheet->getHighestRow();
        $highestColumn = $this->sheet->getHighestColumn();
        $columns = range('A', $highestColumn);

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
        // todo
        return $this->rows;
    }


}