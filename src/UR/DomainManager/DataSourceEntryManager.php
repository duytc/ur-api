<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
use UR\Service\Import\ImportService;
use UR\Worker\Manager;

class DataSourceEntryManager implements DataSourceEntryManagerInterface
{
    use ConvertFileEncoding;
    protected $om;
    protected $repository;
    private $uploadFileDir;
    private $workerManager;
    private $importService;

    public function __construct(ObjectManager $om, DataSourceEntryRepositoryInterface $repository, Manager $workerManager, $uploadFileDir, ImportService $importService)
    {
        $this->om = $om;
        $this->repository = $repository;
        $this->workerManager = $workerManager;
        $this->uploadFileDir = $uploadFileDir;
        $this->importService = $importService;
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
        $newFields = $this->importService->getNewFieldsFromFiles($this->uploadFileDir . $dataSourceEntry->getPath(), $dataSourceEntry->getDataSource());
        $detectedFields = $this->importService->detectedFieldsForDataSource($newFields, $dataSourceEntry->getDataSource()->getDetectedFields(), ImportService::DELETE);
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
    public function uploadDataSourceEntryFile(UploadedFile $file, $path, $dirItem, DataSourceInterface $dataSource, $receivedVia = DataSourceEntry::RECEIVED_VIA_UPLOAD, $alsoMoveFile = true, $metadata = null)
    {
        /* validate via type */
        if (!DataSourceEntry::isSupportedReceivedViaType($receivedVia)) {
            throw new \Exception(sprintf("receivedVia %s is not supported", $receivedVia));
        }

        $error = $this->importService->validateFileUpload($file, $dataSource);
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

        $convertResult = $this->convertToUtf8($filePath, $this->importService->getKernelRootDir());
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
            ->setHashFile($hash)
            ->setMetaData($metadata);

        $newFields = $this->importService->getNewFieldsFromFiles($filePath, $dataSource);
        $detectedFields = $this->importService->detectedFieldsForDataSource($newFields, $dataSource->getDetectedFields(), ImportService::UPLOAD);
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

    private function fileAlreadyImported(DataSourceInterface $dataSource, $hash)
    {
        $importedFiles = $this->repository->getImportedFileByHash($dataSource, $hash);
        return is_array($importedFiles) && count($importedFiles) > 0;
    }
}