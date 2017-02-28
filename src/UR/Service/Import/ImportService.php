<?php

namespace UR\Service\Import;


use Liuggio\ExcelBundle\Factory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use UR\Behaviors\ConvertFileEncoding;
use UR\Model\Core\DataSourceInterface;
use UR\Service\DataSource\Csv;
use UR\Service\DataSource\DataSourceType;
use UR\Service\DataSource\Excel;
use UR\Service\DataSource\Json;

class ImportService
{
    use ConvertFileEncoding;
    const UPLOAD = 'upload';
    const DELETE = 'delete';
    protected $uploadRootDir;
    protected $kernelRootDir;
    private $phpExcel;

    /**
     * ImportService constructor.
     * @param $uploadRootDir
     * @param $kernelRootDir
     * @param Factory $phpExcel
     */
    public function __construct($uploadRootDir, $kernelRootDir, Factory $phpExcel)
    {
        $this->uploadRootDir = $uploadRootDir;
        $this->kernelRootDir = $kernelRootDir;
        $this->phpExcel = $phpExcel;
    }

    public function detectedFieldsFromFiles(FileBag $files, $dirItem, DataSourceInterface $dataSource)
    {
        $uploadPath = $this->uploadRootDir . $dirItem;

        $keys = $files->keys();
        $currentFields = $dataSource->getDetectedFields();
        $name = "";

        foreach ($keys as $key) {
            /**@var UploadedFile $file */
            $file = $files->get($key);

            $error = $this->validateFileUpload($file, $dataSource);
            $origin_name = $file->getClientOriginalName();
            if ($error > 0) {
                throw new \Exception(sprintf("File %s is not valid - wrong format", $origin_name));
            }

            $file_name = basename($origin_name, '.' . $file->getClientOriginalExtension());

            $name = $file_name . '_' . round(microtime(true)) . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $name);
            $filePath = $uploadPath . '/' . $name;

            $convertResult = $this->convertToUtf8($filePath, $this->kernelRootDir);
            if ($convertResult) {
                $newFields = $this->getNewFieldsFromFiles($filePath, $dataSource);
                if (count($newFields) < 1) {
                    throw new \Exception(sprintf("Cannot detect header of File %s", $origin_name));
                }

                $currentFields = $this->detectedFieldsForDataSource($newFields, $currentFields, self::UPLOAD);
            }
        }

        return ["fields" => $currentFields, "filePath" => $dirItem . '/' . $name];
    }

    public function validateFileUpload(UploadedFile $file, DataSourceInterface $dataSource)
    {
        if (strcmp(DataSourceType::DS_EXCEL_FORMAT, $dataSource->getFormat()) === 0) {
            if (!DataSourceType::isExcelType($file->getClientOriginalExtension())) {
                return 1;
            }

        } else if (strcmp(DataSourceType::DS_CSV_FORMAT, $dataSource->getFormat()) === 0) {
            if (!DataSourceType::isCsvType($file->getClientOriginalExtension())) {
                return 2;
            }

        } else if (strcmp(DataSourceType::DS_JSON_FORMAT, $dataSource->getFormat()) === 0) {
            if (!DataSourceType::isJsonType($file->getClientOriginalExtension())) {
                return 3;
            }

        } else {
            return 4;
        }

        return 0;
    }

    public function detectedFieldsForDataSource(array $newFields, array $currentFields, $option)
    {
        foreach ($newFields as $newField) {
            $currentFields = $this->updateDetectedField($newField, $currentFields, $option);
        }
        return $currentFields;
    }

    public function getNewFieldsFromFiles($inputFile, DataSourceInterface $dataSource)
    {
        /**@var \UR\Service\DataSource\DataSourceInterface $file */

        if (strcmp($dataSource->getFormat(), 'csv') === 0) {
            /**@var Csv $file */
            $file = new Csv($inputFile);
        } else if (strcmp($dataSource->getFormat(), 'excel') === 0) {
            /**@var Excel $file */
            $file = new Excel($inputFile, $this->phpExcel, 5000);
        } else if (strcmp($dataSource->getFormat(), 'json') === 0) {
            $file = new Json($inputFile);
        }

        $newFields = [];
        $columns = $file->getColumns();
        if ($columns === null) {
            return [];

        }

        $newFields = array_merge($newFields, $columns);
        $newFields = array_unique($newFields);
        $newFields = array_filter($newFields, function ($value) {
            if (is_numeric($value)) {
                return true;
            }

            return ($value !== '' && $value !== null);
        });

        return $newFields;
    }

    private function updateDetectedField($newField, $detectedFields, $option)
    {
        $newField = strtolower(trim($newField));
        switch ($option) {
            case self::UPLOAD:
                if (!array_key_exists($newField, $detectedFields)) {
                    $detectedFields[$newField] = 1;
                } else {
                    $detectedFields[$newField] += 1;
                }
                break;
            case self::DELETE:
                if (isset($detectedFields[$newField])) {
                    $detectedFields[$newField] -= 1;
                    if ($detectedFields[$newField] <= 0) {
                        unset($detectedFields[$newField]);
                    }
                }
                break;
        }

        return $detectedFields;
    }

    /**
     * @return mixed
     */
    public function getKernelRootDir()
    {
        return $this->kernelRootDir;
    }
}