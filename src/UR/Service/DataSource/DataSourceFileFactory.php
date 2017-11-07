<?php

namespace UR\Service\DataSource;


use Liuggio\ExcelBundle\Factory;
use \Exception;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Import\ImportDataException;
use UR\Service\PublicSimpleException;

class DataSourceFileFactory
{
    private $uploadFileDir;

    /**
     * @var Factory
     */
    private $phpExcel;

    private $chunkSize;

    private $rowThreshold;

    private $tempFileDir;

    /**
     * FileFactory constructor.
     * @param Factory $phpExcel
     * @param $chunkSize
     * @param $uploadFileDir
     * @param $rowThreshold
     * @param $tempFileDir
     */
    public function __construct(Factory $phpExcel, $chunkSize, $uploadFileDir, $rowThreshold, $tempFileDir)
    {
        $this->uploadFileDir = $uploadFileDir;
        $this->phpExcel = $phpExcel;
        $this->chunkSize = $chunkSize;
        $this->rowThreshold = $rowThreshold;
        $this->tempFileDir = $tempFileDir;
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

        return $this->getEntryFile($fileType, $filePath);
    }

    /**
     * @param $entryPath
     * @return Csv|Excel|Excel2007|Json|JsonNewFormat
     * @throws Exception
     * @throws ImportDataException
     */
    public function getFileForChunk($entryPath)
    {
        $filePath = $entryPath;

        if (!file_exists($filePath)) {
            $filePath = $this->uploadFileDir . $entryPath;
        }

        if (!file_exists($filePath)) {
            throw new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILE_NOT_FOUND);
        }

        $fileType = DataSourceType::getOriginalDataSourceType(pathinfo($entryPath, PATHINFO_EXTENSION));

        return $this->getEntryFile($fileType, $filePath);
    }

    public function getAbsolutePath($path) {
        if (is_file($path)) {
            return $path;
        }

        $longPath = $this->uploadFileDir . $path;

        if (is_file($longPath)) {
            return $longPath;
        }

        throw new PublicSimpleException(sprintf("Can not get absolute path for %s", $path));
    }

    /**
     * @param $fileType
     * @param $filePath
     * @return Csv|Excel|Excel2007|Json
     * @throws Exception
     */
    private function getEntryFile($fileType, $filePath) {
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

    /**
     * Split huge file to many csv files
     * @param DataSourceEntryInterface $dataSourceEntry
     * @throws Exception
     * @throws ImportDataException
     * @return DataSourceEntryInterface
     */
    public function splitHugeFile(DataSourceEntryInterface $dataSourceEntry)
    {
        //create directory to store split file
        $filePath = $this->uploadFileDir . $dataSourceEntry->getPath();
        $splitDirectory = join('/', array($this->getSourceDirName($filePath) , 'SplitDirectory'));
        if (!is_dir($splitDirectory)) {
            mkdir($splitDirectory, 0777, true);
        }

        gc_enable();
        $file = $this->getFile($dataSourceEntry->getDataSource()->getFormat(), $dataSourceEntry->getPath());

        $chunks = [];
        $bodyRows = $file->getRows();
        $header = $file->getHeaders();
        $rowCount = 0;
        $chunkCount = 0;

        $outputFileName = '';
        $fileClosed = false;
        $newFile = null;
        foreach ($bodyRows as $bodyRow) {
            $fileClosed = false;
            if ($rowCount % $this->rowThreshold == 0) {
                $chunkCount++;
                $outputFileName = sprintf("entry_%s_part_%s_random_%s.csv", $dataSourceEntry->getId(), $chunkCount, uniqid((new \DateTime())->format('Y-m-d'), true));
                $outputFileName = join('/', array($splitDirectory, $outputFileName));
                $newFile = fopen($outputFileName, 'w');
                fputcsv($newFile, $header);
            }

            if (is_resource($newFile)) {
                fputcsv($newFile, $bodyRow);
            }

            if ($rowCount % $this->rowThreshold == $this->rowThreshold - 1) {
                fclose($newFile);
                $outputFileName = str_replace($this->uploadFileDir, '', $outputFileName);
                $chunks[] = $outputFileName;
                $fileClosed = true;
            }

            $rowCount++;
        }

        if (!$fileClosed) {
            fclose($newFile);
            unset($newFile);
            $outputFileName = str_replace($this->uploadFileDir, '', $outputFileName);
            $chunks[] = $outputFileName;
        }

        unset($bodyRow, $bodyRows, $file, $newFile);
        gc_collect_cycles();

        if (!empty($chunks)) {
            $dataSourceEntry->setSeparable(true);
            $dataSourceEntry->setChunks($chunks);
        }

        if (!empty($rowCount)) {
            $dataSourceEntry->setTotalRow($rowCount);
        }

        return $dataSourceEntry;
    }

    /**
     * @param $filePath
     * @return mixed
     */
    private function getSourceDirName($filePath)
    {
        $sourceDir =  pathinfo($filePath, PATHINFO_DIRNAME);

        return $sourceDir;
    }
}