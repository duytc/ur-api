<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use Liuggio\ExcelBundle\Factory;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use UR\Behaviors\ConvertFileEncoding;
use UR\Entity\Core\DataSourceEntry;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\DataSourceEntryRepositoryInterface;
use ReflectionClass;
use UR\Repository\Core\DataSourceRepository;
use UR\Service\Alert\ProcessAlert;
use UR\Service\DataSource\Csv;
use UR\Service\DataSource\DataSourceType;
use UR\Service\DataSource\Excel;
use UR\Service\DataSource\Json;
use UR\Worker\Manager;

class DataSourceEntryManager implements DataSourceEntryManagerInterface
{
    use ConvertFileEncoding;
    const UPLOAD = 'upload';
    const DELETE = 'delete';

    protected $om;
    protected $repository;
    private $uploadFileDir;
    private $phpExcel;
    protected $workerManager;

    public function __construct(ObjectManager $om, DataSourceEntryRepositoryInterface $repository, Manager $workerManager, $uploadFileDir, Factory $phpExcel)
    {
        $this->om = $om;
        $this->repository = $repository;
        $this->workerManager = $workerManager;
        $this->uploadFileDir = $uploadFileDir;
        $this->phpExcel = $phpExcel;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, DataSourceEntryInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $dataSourceEntry)
    {
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) throw new InvalidArgumentException('expect DataSourceEntryInterface Object');
        $this->om->persist($dataSourceEntry);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $dataSourceEntry)
    {
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) throw new InvalidArgumentException('expect DataSourceEntryInterface Object');
        $this->om->remove($dataSourceEntry);
        $newFields = $this->getNewFieldsFromFiles($this->uploadFileDir . $dataSourceEntry->getPath(), $dataSourceEntry->getDataSource());
        $detectedFields = $this->detectedFieldsForDataSource($newFields, $dataSourceEntry->getDataSource()->getDetectedFields(), DataSourceEntryManager::DELETE);
        $dataSourceEntry->getDataSource()->setDetectedFields($detectedFields);
        $this->om->flush();
        if (file_exists($this->uploadFileDir . $dataSourceEntry->getPath())) {
            unlink($this->uploadFileDir . $dataSourceEntry->getPath());
        }
    }

    /**
     * @inheritdoc
     */
    public function createNew()
    {
        $entity = new ReflectionClass($this->repository->getClassName());
        return $entity->newInstance();
    }

    /**
     * @inheritdoc
     */
    public function find($id)
    {
        return $this->repository->find($id);
    }

    /**
     * @inheritdoc
     */
    public function all($limit = null, $offset = null)
    {
        return $this->repository->findBy($criteria = [], $orderBy = null, $limit, $offset);
    }

    /**
     * @inheritdoc
     */
    public function detectedFieldsFromFiles(FileBag $files, $uploadPath, $dirItem, DataSourceInterface $dataSource)
    {
        $keys = $files->keys();
        $currentFields = [];

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

            $convertResult = $this->convertToUtf8($filePath);
            if ($convertResult) {
                $newFields = $this->getNewFieldsFromFiles($filePath, $dataSource);
                unlink($filePath);
                if (count($newFields) < 1) {
                    throw new \Exception(sprintf("Cannot detect header of File %s", $origin_name));
                }

                $currentFields = $this->detectedFieldsForDataSource($newFields, $currentFields, self::UPLOAD);
            }
        }

        return $currentFields;
    }

    /**
     * @inheritdoc
     */
    public function uploadDataSourceEntryFile(UploadedFile $file, $path, $dirItem, DataSourceInterface $dataSource, $receivedVia = DataSourceEntry::RECEIVED_VIA_UPLOAD, $alsoMoveFile)
    {
        /* validate via type */
        if (!DataSourceEntry::isSupportedReceivedViaType($receivedVia)) {
            throw new \Exception(sprintf("receivedVia %s is not supported", $receivedVia));
        }

        $error = $this->validateFileUpload($file, $dataSource);
        $origin_name = $file->getClientOriginalName();
        $file_name = basename($origin_name, '.' . $file->getClientOriginalExtension());

        if ($error > 0) {
            if (in_array(DataSourceRepository::WRONG_FORMAT, $dataSource->getAlertSetting())) {
                $code = ProcessAlert::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT;
                $publisherId = $dataSource->getPublisher()->getId();

                $params = array(
                    ProcessAlert::FILE_NAME => $file_name . "." . $file->getClientOriginalExtension(),
                    ProcessAlert::DATA_SOURCE_NAME => $dataSource->getName()
                );

                $this->workerManager->processAlert($code, $publisherId, $params);
            }

            throw new \Exception(sprintf("File %s is not valid - wrong format", $origin_name));
        }

        // save file to upload dir
        if (strcmp($receivedVia, DataSourceEntry::RECEIVED_VIA_API) === 0) {
            $name = $origin_name;
        } else {
            $name = $file_name . '_' . round(microtime(true)) . '.' . $file->getClientOriginalExtension();
        }

        if ($alsoMoveFile) {
            $file->move($path, $name);
        } else {
            if (!file_exists($path)) {
                mkdir($path, 0755, true);
            }
            copy($file->getRealPath(), $path . '/' . $name);
        }

        $filePath = $path . '/' . $name;

        $convertResult = $this->convertToUtf8($filePath);
        if (!$convertResult) {
            throw new \Exception(sprintf("File %s is not valid - cannot convert to UTF-8", $origin_name));
        }

        $hash = sha1_file($filePath);
        if ($this->fileAlreadyImported($dataSource, $hash)) {
            throw new Exception(sprintf('File "%s" is already imported', $origin_name));
        }

        // create new data source entry
        $dataSourceEntry = new DataSourceEntry();
        $dataSourceEntry->setPath($dirItem . '/' . $name)
            ->setIsValid(true)
            ->setReceivedVia($receivedVia)
            ->setFileName($origin_name)
            ->setHashFile($hash);

        $newFields = $this->getNewFieldsFromFiles($filePath, $dataSource);
        $detectedFields = $this->detectedFieldsForDataSource($newFields, $dataSource->getDetectedFields(), DataSourceEntryManager::UPLOAD);
        $dataSource->setDetectedFields($detectedFields);

        $dataSourceEntry->setDataSource($dataSource);
        $this->save($dataSourceEntry);

        if (in_array(DataSourceRepository::DATA_RECEIVED, $dataSource->getAlertSetting())) {
            $code = ProcessAlert::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD;
            $publisherId = $dataSource->getPublisher()->getId();

            $params = array(
                ProcessAlert::FILE_NAME => $file_name . "." . $file->getClientOriginalExtension(),
                ProcessAlert::DATA_SOURCE_NAME => $dataSource->getName()
            );

            $this->workerManager->processAlert($code, $publisherId, $params);
        }

        $result = [
            'file' => $origin_name,
            'status' => true,
            'message' => sprintf('File %s is uploaded successfully', $origin_name)
        ];

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceEntryForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        return $this->repository->getDataSourceEntriesForPublisher($publisher, $limit, $offset);
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

    public function detectedFieldsForDataSource(array $newFields, array $currentFields, $option)
    {
        foreach ($newFields as $newField) {
            $currentFields = $this->updateDetectedField($newField, $currentFields, $option);
        }
        return $currentFields;
    }

    private function updateDetectedField($newField, $detectedFields, $option)
    {
        $newField = strtolower(trim($newField));
        switch ($option) {
            case DataSourceEntryManager::UPLOAD:
                if (!array_key_exists($newField, $detectedFields)) {
                    $detectedFields[$newField] = 1;
                } else {
                    $detectedFields[$newField] += 1;
                }
                break;
            case DataSourceEntryManager::DELETE:
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

    private function fileAlreadyImported(DataSourceInterface $dataSource, $hash)
    {
        $importedFiles = $this->repository->getImportedFileByHash($dataSource, $hash);
        return is_array($importedFiles) && count($importedFiles) > 0;
    }
}