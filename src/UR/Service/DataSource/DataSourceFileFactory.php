<?php

namespace UR\Service\DataSource;


use Liuggio\ExcelBundle\Factory;
use \Exception;
use UR\Model\Core\AlertInterface;
use UR\Service\Import\ImportDataException;

class DataSourceFileFactory
{
    private $uploadFileDir;

    /**
     * @var Factory
     */
    private $phpExcel;

    private $chunkSize;

    /**
     * FileFactory constructor.
     * @param Factory $phpExcel
     * @param $chunkSize
     * @param $uploadFileDir
     */
    public function __construct(Factory $phpExcel, $chunkSize, $uploadFileDir)
    {
        $this->uploadFileDir = $uploadFileDir;
        $this->phpExcel = $phpExcel;
        $this->chunkSize = $chunkSize;
    }

    /**
     * @param string $fileType
     * @param $entryPath
     * @return Csv|Excel|Excel2007|Json|JsonNewFormat
     * @throws Exception
     * @throws ImportDataException
     */
    public function getFile($fileType, $entryPath)
    {
        $filePath = $this->uploadFileDir . $entryPath;
        if (!file_exists($filePath)) {
            throw new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILE_NOT_FOUND);
        }

        switch ($fileType) {
            case DataSourceType::DS_CSV_FORMAT:
                return new Csv($filePath);
            case DataSourceType::DS_EXCEL_FORMAT:
                $inputFileType = \PHPExcel_IOFactory::identify($filePath);
                if (in_array($inputFileType, Excel::$EXCEL_2003_FORMATS)) {
                    return new Excel($filePath, $this->chunkSize);
                } else if (in_array($inputFileType, Excel2007::$EXCEL_2007_FORMATS)) {
                    return new Excel2007($filePath, $this->chunkSize);
                } else {
                    throw new Exception(sprintf('Does not support this Excel type'));
                }

            case DataSourceType::DS_JSON_FORMAT:
                try {
                    return new Json($filePath);
                } catch (\Exception $ex) {
                    throw $ex;
                }

            default:
                throw new Exception(sprintf('Does not support this file type'));
        }
    }
}