<?php

namespace UR\Service\Import;


use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use UR\Behaviors\ConvertFileEncoding;
use UR\Model\Core\DataSourceInterface;
use UR\Service\DataSource\DataSourceType;
use UR\Service\DataSource\DataSourceFileFactory;

class ImportService
{
    use ConvertFileEncoding;
    const UPLOAD = 'upload';
    const DELETE = 'delete';
    protected $uploadRootDir;
    protected $kernelRootDir;
    private $fileFactory;

    /**
     * ImportService constructor.
     * @param $uploadRootDir
     * @param $kernelRootDir
     * @param DataSourceFileFactory $fileFactory
     */
    public function __construct($uploadRootDir, $kernelRootDir, DataSourceFileFactory $fileFactory)
    {
        $this->uploadRootDir = $uploadRootDir;
        $this->kernelRootDir = $kernelRootDir;
        $this->fileFactory = $fileFactory;
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

            $isValidFile = $this->validateFileUpload($file, $dataSource);
            $origin_name = $file->getClientOriginalName();
            if (!$isValidFile) {
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

    /**
     * validate File Upload
     *
     * @param UploadedFile $file
     * @param DataSourceInterface $dataSource
     * @return bool
     */
    public function validateFileUpload(UploadedFile $file, DataSourceInterface $dataSource)
    {
        return $dataSource->getFormat() == DataSourceType::getOriginalDataSourceType($file->getClientOriginalExtension());
    }

    /**
     * validate extension support or not
     *
     * @param UploadedFile $file
     * @return bool
     */
    public function validateExtensionSupports(UploadedFile $file)
    {
        // check if in supported formats
        if (!DataSourceType::isSupportedExtension($file->getClientOriginalExtension())) {
            return false;
        }
        return true;
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
        $file = $this->fileFactory->getFile($dataSource->getFormat(), $inputFile);

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