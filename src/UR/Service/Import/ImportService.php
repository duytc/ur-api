<?php

namespace UR\Service\Import;

use SplFileObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use UR\Behaviors\FileUtilsTrait;
use UR\Model\Core\DataSourceInterface;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Service\DataSource\DataSourceType;

class ImportService
{
    use FileUtilsTrait;

    const ACTION_UPLOAD = 'upload';
    const ACTION_DELETE = 'delete';

    /** @var string */
    protected $uploadRootDir;
    /** @var string */
    protected $kernelRootDir;
    /**
     * @var DataSourceFileFactory
     */
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

    /**
     * detect Fields From Files
     *
     * @param FileBag $files
     * @param string $dirItem
     * @param DataSourceInterface $dataSource
     * @return array format as ['fields' => <detected fields>, 'filePath' => <dirItem/filename>];
     * @throws \Exception
     */
    public function detectFieldsFromFiles(FileBag $files, $dirItem, DataSourceInterface $dataSource)
    {
        $uploadPath = $this->uploadRootDir . $dirItem;

        $keys = $files->keys();
        $currentFields = $dataSource->getDetectedFields();

        foreach ($keys as $key) {
            /**@var UploadedFile $file */
            $file = $files->get($key);

            $isValidFile = $this->validateUploadedFile($file, $dataSource);
            $origin_name = $file->getClientOriginalName();
            if (!$isValidFile) {
                throw new \Exception(sprintf('File %s is not valid - wrong format', $origin_name));
            }

            $filename = basename($origin_name, '.' . $file->getClientOriginalExtension());

            // escape $filename (remove special characters)
            $filename = $this->escapeFileNameContainsSpecialCharacters($filename);

            $name = $filename . '_' . round(microtime(true)) . '.' . $file->getClientOriginalExtension();

            $file->move($uploadPath, $name);
            $filePath = $uploadPath . '/' . $name;

            $convertResult = $this->convertToUtf8($filePath, $this->kernelRootDir);
            if ($convertResult) {
                $fileName = sprintf('%s/%s', $dirItem, $name);
                $fileFormat = $this->getUploadFileFormat($fileName);

                $dataSourceFile = $this->getDataSourceFile($fileFormat, $fileName);
                $newFields = $this->getNewFieldsFromFiles($dataSourceFile);
                if (empty($newFields) && !empty($currentFields)) {
                    throw new \Exception(sprintf('Cannot detect header of File %s', $origin_name));
                }

                $currentFields = $this->detectFieldsForDataSource($newFields, $currentFields, self::ACTION_UPLOAD);
            }

            return ['fields' => $currentFields, 'filePath' => $dirItem . '/' . $name, 'fileName' => $origin_name];
        }
    }

    /**
     * Get format of upload file
     * @param $filePath
     * @return string
     * @throws \Exception
     */
    private function getUploadFileFormat($filePath) {

        $fileExtension = pathinfo($filePath, PATHINFO_EXTENSION);

        if (in_array($fileExtension, DataSourceType::$EXCEL_TYPES )){
            return DataSourceType::DS_EXCEL_FORMAT;
        }

        if (in_array($fileExtension, DataSourceType::$JSON_TYPES )){
            return DataSourceType::DS_JSON_FORMAT;
        }

        if (in_array($fileExtension, DataSourceType::$CSV_TYPES )){
            return DataSourceType::DS_CSV_FORMAT;
        }

        throw new \Exception(sprintf('Does not support file with extension %s'), $fileExtension);
    }

    /**
     * validate Uploaded File
     *
     * @param UploadedFile $file
     * @param DataSourceInterface $dataSource
     * @return bool
     */
    public function validateUploadedFile(UploadedFile $file, DataSourceInterface $dataSource)
    {
        $dataSourceTypeExtension = DataSourceType::getOriginalDataSourceType($file->getClientOriginalExtension());

        // allow smart-change type if have no entry before
        // e.g if no entry before and current csv => switch to excel if new entry is an excel file
        if (count($dataSource->getDataSourceEntries()) < 1) {
            return true;
        }

        return $dataSource->getFormat() == $dataSourceTypeExtension;
    }

    /**
     * detect Fields For Data Source
     *
     * @param array $newDetectedFields
     * @param array $currentDetectedFields
     * @param string $action UPLOAD or DELETE
     * @return array|mixed
     */
    public function detectFieldsForDataSource(array $newDetectedFields, array $currentDetectedFields, $action)
    {
        foreach ($newDetectedFields as $newDetectedField) {
            $currentDetectedFields = $this->updateDetectedFields($newDetectedField, $currentDetectedFields, $action);
        }

        return $currentDetectedFields;
    }

    /**
     * get New Fields From Files
     *
     * @param \UR\Service\DataSource\DataSourceInterface $file
     * @return array
     */
    public function getNewFieldsFromFiles(\UR\Service\DataSource\DataSourceInterface $file)
    {
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

    /**
     * update Detected Fields
     *
     * @param string $newDetectedField
     * @param array $currentDetectedFields
     * @param string $action UPLOAD OR DELETE
     * @return mixed
     */
    private function updateDetectedFields($newDetectedField, array $currentDetectedFields, $action)
    {
        $newDetectedField = strtolower(trim($newDetectedField));

        switch ($action) {
            case self::ACTION_UPLOAD:
                if (!array_key_exists($newDetectedField, $currentDetectedFields)) {
                    $currentDetectedFields[$newDetectedField] = 1;
                } else {
                    $currentDetectedFields[$newDetectedField] += 1;
                }

                break;

            case self::ACTION_DELETE:
                if (isset($currentDetectedFields[$newDetectedField])) {
                    $currentDetectedFields[$newDetectedField] -= 1;
                    if ($currentDetectedFields[$newDetectedField] <= 0) {
                        unset($currentDetectedFields[$newDetectedField]);
                    }
                }

                break;
        }

        return $currentDetectedFields;
    }

    /**
     * @return mixed
     */
    public function getKernelRootDir()
    {
        return $this->kernelRootDir;
    }

    /**
     * @param $format
     * @param $entryPath
     * @return \UR\Service\DataSource\DataSourceInterface
     */
    public function getDataSourceFile($format, $entryPath)
    {
        /**@var \UR\Service\DataSource\DataSourceInterface $file */
        return $this->fileFactory->getFile($format, $entryPath);
    }

    public function fixCSVLineFeed($filePath)
    {

        $copyPath = $filePath . 'copy' . time();
        try {
            rename($filePath, $copyPath);

            $file = fopen($filePath, 'w');

            foreach (new SplFileObject($copyPath) as $lineNumber => $lineContent) {
                $fixCLRFContent = str_replace("\r", "\n", $lineContent);
                fwrite($file, $fixCLRFContent);
            }

            fclose($file);
        } catch (\Exception $exception) {
            throw new \Exception(sprintf('Cannot fix window line feed for This file with path: %s', $filePath));
        }

        if (is_file($copyPath)) {
            unlink($copyPath);
        }
    }
}