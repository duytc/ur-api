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
        $highestColumn = $this->sheet->getHighestColumn();

        $headings = $this->sheet->rangeToArray('A1:' . $highestColumn . 1,
            NULL,
            TRUE,
            FALSE);
        foreach ($headings as $heading) {
            $this->headers = $heading;
            break;
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