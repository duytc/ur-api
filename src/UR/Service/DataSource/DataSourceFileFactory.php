<?php

namespace UR\Service\DataSource;


use Liuggio\ExcelBundle\Factory;
use Symfony\Component\Config\Definition\Exception\Exception;

class DataSourceFileFactory
{
    /**
     * @var Factory
     */
    private $phpExcel;

    private $chunkSize;

    /**
     * FileFactory constructor.
     * @param Factory $phpExcel
     * @param $chunkSize
     */
    public function __construct(Factory $phpExcel, $chunkSize)
    {
        $this->phpExcel = $phpExcel;
        $this->chunkSize = $chunkSize;
    }

    /**
     * @param $fileType
     * @param $filePath
     * @return Csv|Excel|Json
     */
    public function getFile($fileType, $filePath)
    {
        switch ($fileType) {
            case DataSourceType::DS_CSV_FORMAT:
                return new Csv($filePath);
            case DataSourceType::DS_EXCEL_FORMAT:
                return new Excel($filePath, $this->phpExcel, $this->chunkSize);
            case DataSourceType::DS_JSON_FORMAT:
                return new Json($filePath);
            default:
                throw new Exception(sprintf('Dose not support this file type'));
        }
    }
}