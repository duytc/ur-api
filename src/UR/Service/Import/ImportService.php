<?php

namespace UR\Service\Import;


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
        $name = '';

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
                $newFields = $this->getNewFieldsFromFiles($dirItem . '/' . $name, $dataSource);
                if (count($newFields) < 1) {
                    throw new \Exception(sprintf('Cannot detect header of File %s', $origin_name));
                }

                $currentFields = $this->detectFieldsForDataSource($newFields, $currentFields, self::ACTION_UPLOAD);
            }
        }

        return ['fields' => $currentFields, 'filePath' => $dirItem . '/' . $name];
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
     * @param string $inputFile
     * @param DataSourceInterface $dataSource
     * @return array
     */
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
}