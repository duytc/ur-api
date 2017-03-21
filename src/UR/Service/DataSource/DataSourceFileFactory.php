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
     * @return Csv|Excel|Json|JsonNewFormat
     */
    public function getFile($fileType, $filePath)
    {
        switch ($fileType) {
            case DataSourceType::DS_CSV_FORMAT:
                return new Csv($filePath);
            case DataSourceType::DS_EXCEL_FORMAT:
                return new Excel($filePath, $this->phpExcel, $this->chunkSize);
            case DataSourceType::DS_JSON_FORMAT:
                $str = file_get_contents($filePath, true);
                $json = json_decode($str, true);

                if ($json != null && array_key_exists('rows', $json) && array_key_exists('columns', $json)) {
                    return new JsonNewFormat($filePath);
                }

                return new Json($filePath);
            default:
                throw new Exception(sprintf('Dose not support this file type'));
        }
    }
}