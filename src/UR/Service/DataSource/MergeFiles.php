<?php

namespace UR\Service\DataSource;


use Exception;
use PHPExcel;
use PHPExcel_IOFactory;
use PHPExcel_Writer_Exception;

class MergeFiles
{
    private static $FILE_EXTENSIONS = ['csv', 'json', 'xls', 'xlm', 'xlsx', 'xlsm', 'xltx', 'xltm', 'xlsb', 'xla', 'xlam', 'xll', 'xlw'];
    private static $CHUNK_SIZE = 100;
    protected $importHistoryId;

    private $sourceFilePaths;
    private $outputFilePath;

    /**
     * MergeFiles constructor.
     * @param $sourceFilePaths
     * @param $outputFilePath
     * @param $importHistoryId
     * @throws Exception
     */
    public function __construct($sourceFilePaths, $outputFilePath = null, $importHistoryId)
    {
        if (!$this->validateSourceFiles($sourceFilePaths)) {
            return;
        }
        $this->sourceFilePaths = $sourceFilePaths;

        if (is_null($outputFilePath)) {
            $this->outputFilePath = sprintf("%s/%s", $this->getSourceDirName(), 'MergedFiles');
        } else {
            $this->validateOutputFilePath($outputFilePath);
            $this->outputFilePath = $outputFilePath;
        }

        $this->importHistoryId = $importHistoryId;
    }

    /**
     * @param $sourceFilePaths
     * @return bool
     * @throws Exception
     */
    private function validateSourceFiles($sourceFilePaths)
    {
        if (!is_array($sourceFilePaths)) {
            throw new Exception('Merge files requires array input');
        }

        if (empty($sourceFilePaths)) {
            throw new Exception('Source files must not empty');
        }

        $firstFileExtension = pathinfo($sourceFilePaths[0], PATHINFO_EXTENSION);

        foreach ($sourceFilePaths as $sourceFilePath) {

            if (!file_exists($sourceFilePath)) {
                throw new Exception(sprintf('Source file %s does not exits', $sourceFilePath));
            }

            $fileExtension = pathinfo($sourceFilePath, PATHINFO_EXTENSION);
            if ($fileExtension !== $firstFileExtension || !in_array($fileExtension, self::$FILE_EXTENSIONS)) {
                throw  new Exception(sprintf('Not support merging this %s extension file or this extension is differs others', $fileExtension));
            }
        }

        return true;
    }

    /**
     * @return mixed
     */

    private function getSourceDirName()
    {
        $sourceDir = pathinfo($this->sourceFilePaths[0], PATHINFO_DIRNAME);

        return $sourceDir;
    }

    /**
     * @return mixed
     */
    private function getSourceExtensionFile()
    {
        return pathinfo($this->sourceFilePaths[0], PATHINFO_EXTENSION);
    }

    /**
     * @param $outputFilePath
     * @return bool
     * @throws Exception
     */
    private function validateOutputFilePath($outputFilePath)
    {
        if (!is_null($outputFilePath) && !is_dir($outputFilePath)) {
            throw new Exception('Output file path does not exits');
        }

        return true;
    }

    /**
     * Combile many files into one file
     */
    public function mergeFiles()
    {
        $fileExtension = $this->getSourceExtensionFile();
        $fileType = $this->getFileType($fileExtension);

        switch ($fileType) {
            case DataSourceType::DS_CSV_FORMAT:
                return $this->mergedCSVFiles();
            case DataSourceType::DS_EXCEL_FORMAT:
                return $this->mergedExcelFiles();
            case DataSourceType::DS_JSON_FORMAT:
                return $this->mergedJsonFiles();
            default:
                throw new Exception(sprintf('Not support type %s merging files', $fileType));
        }
    }

    /**
     * Get file type from file extension
     * @param $extensionFile
     * @return bool|string
     */
    private function getFileType($extensionFile)
    {
        if (in_array($extensionFile, DataSourceType::$JSON_TYPES)) {
            return DataSourceType::DS_JSON_FORMAT;
        }

        if (in_array($extensionFile, DataSourceType::$EXCEL_TYPES)) {
            return DataSourceType::DS_EXCEL_FORMAT;
        }

        if (in_array($extensionFile, DataSourceType::$CSV_TYPES)) {
            return DataSourceType::DS_CSV_FORMAT;
        }

        return false;
    }

    /**
     * Merge many excel files to one excel file
     */
    private function mergedExcelFiles()
    {
        $outputFileName = $this->getOutputFileName();

        $header = null;
        $rows = null;

        foreach ($this->sourceFilePaths as $sourceFilePath) {
            $excelObj = new Excel($sourceFilePath, self::$CHUNK_SIZE);
            if (is_null($header)) {
                $header = $excelObj->getHeaderRow();
                $rows[] = $header;
            }
            $bodyRows = $excelObj->getBodyRows();
            $rows = array_merge($rows, $bodyRows);
        }

        $doc = new PHPExcel();
        $doc->setActiveSheetIndex(0);

        $doc->getActiveSheet()->fromArray($rows, null, 'A1');
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="mergedFile.xls"');
        header('Cache-Control: max-age=0');

        $writer = PHPExcel_IOFactory::createWriter($doc, 'Excel5');
        try {
            $writer->save($outputFileName);
        } catch (PHPExcel_Writer_Exception $e) {
            return false;
        }

        return $outputFileName;
    }

    /**
     * Merge many csv files to one csv file
     */
    private function mergedCSVFiles()
    {
        $outputFileName = $this->getOutputFileName();

        $header = null;
        $rows = null;

        foreach ($this->sourceFilePaths as $sourceFilePath) {
            $csvObj = new Csv($sourceFilePath);
            if (is_null($header)) {
                $header = $csvObj->getHeaders();
                $rows[] = $header;
            }
            $bodyRows = $csvObj->getBodyRows();
            $rows = array_merge($rows, $bodyRows);
        }

        $csvFile = fopen($outputFileName, 'w');
        foreach ($rows as $row) {
            if (!fputcsv($csvFile, $row)) {
                return false;
            }
        }
        fclose($csvFile);

        return $outputFileName;
    }

    /**
     * Combine many json files to one larger json file
     */
    private function mergedJsonFiles()
    {
        $outputFileName = $this->getOutputFileName();

        $header = null;
        $rows = null;

        foreach ($this->sourceFilePaths as $sourceFilePath) {
            $csvObj = new Json($sourceFilePath);
            if (is_null($header)) {
                $header = $csvObj->getHeaders();
                $rows[] = $header;
            }
            $bodyRows = $csvObj->getBodyRows();
            $rows = array_merge($rows, $bodyRows);
        }

        $jsonData = json_encode($rows, JSON_PRETTY_PRINT);
        if (file_put_contents($outputFileName, $jsonData)) {
            return $outputFileName;
        }

        return false;
    }

    /**
     * Get full path of output file name
     * @return string
     */
    private function getOutputFileName()
    {
        if (!is_dir($this->outputFilePath)) {
           if (!mkdir($this->outputFilePath, 0777, true)) {
               new Exception(sprintf(' Can not create folder %s', $this->outputFilePath));
           };
        }

        $fileName = sprintf('%s/import_%s_full.%s', $this->getOutputFilePath(), $this->importHistoryId, $this->getSourceExtensionFile());

        return $fileName;
    }

    /**
     * @return mixed
     */
    public function getSourceFilePaths()
    {
        return $this->sourceFilePaths;
    }

    /**
     * @param mixed $sourceFilePaths
     */
    public function setSourceFilePaths($sourceFilePaths)
    {
        $this->sourceFilePaths = $sourceFilePaths;
    }

    /**
     * @return null
     */
    public function getOutputFilePath()
    {
        return $this->outputFilePath;
    }

    /**
     * @param null $outputFilePath
     */
    public function setOutputFilePath($outputFilePath)
    {
        $this->outputFilePath = $outputFilePath;
    }

}





