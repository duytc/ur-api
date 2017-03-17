<?php

namespace UR\DomainManager;

use DateTime;
use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
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
use UR\Service\Alert\DataSource\AbstractDataSourceAlert;
use UR\Service\Alert\DataSource\DataSourceAlertFactory;
use UR\Service\Alert\DataSource\DataReceivedAlert;
use UR\Service\Alert\DataSource\WrongFormatAlert;
use UR\Service\DataSource\DataSourceType;
use UR\Service\Import\ImportDataException;
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
    private $alertFactory;

    public function __construct(ObjectManager $om, DataSourceEntryRepositoryInterface $repository, Manager $workerManager, $uploadFileDir, ImportService $importService)
    {
        $this->om = $om;
        $this->repository = $repository;
        $this->workerManager = $workerManager;
        $this->uploadFileDir = $uploadFileDir;
        $this->importService = $importService;
        $this->alertFactory = new DataSourceAlertFactory();
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

        $origin_name = $file->getClientOriginalName();
        $file_name = basename($origin_name, '.' . $file->getClientOriginalExtension());
        $publisherId = $dataSource->getPublisher()->getId();

        try {
            // validate file extension before processing upload
            $isExtensionSupport = $this->importService->validateExtensionSupports($file);
            if (!$isExtensionSupport) {
                throw new ImportDataException(WrongFormatAlert::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT, null, null);
            }

            // automatically update data source format if has no entry before
            if (count($dataSource->getDataSourceEntries()) < 1) {
                $rawFileFormat = $file->getClientOriginalExtension();
                $format = DataSourceType::getOriginalDataSourceType($rawFileFormat);
                $dataSource->setFormat($format);
                $this->om->persist($dataSource);
                $this->om->flush();
            }

            $isValidExtension = $this->importService->validateFileUpload($file, $dataSource);
            if (!$isValidExtension) {
                throw new ImportDataException(WrongFormatAlert::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT, null, null);
            }

            // save file to upload dir
            if (strcmp($receivedVia, DataSourceEntry::RECEIVED_VIA_API) === 0) {
                $name = $origin_name;
            } else {
                $name = $file_name . '_' . round(microtime(true)) . '.' . $file->getClientOriginalExtension();
            }

            if (strlen($name) > 230) {
                throw new Exception(sprintf('File Name is too long: name of file should not be more than 230 characters'));
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

            $alert = $this->alertFactory->getAlert(
                DataReceivedAlert::ALERT_CODE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD,
                $origin_name,
                $dataSource);

            if ($alert instanceof DataReceivedAlert) {
                $this->workerManager->processAlert($alert->getAlertCode(), $publisherId, $alert->getAlertMessage(), $alert->getAlertDetails());
            }

        } catch (ImportDataException $exception) {
            $code = $exception->getAlertCode();
            $alert = $this->alertFactory->getAlert(
                $code,
                $origin_name,
                $dataSource);

            if ($alert instanceof AbstractDataSourceAlert) {
                $this->workerManager->processAlert($alert->getAlertCode(), $publisherId, $alert->getAlertMessage(), $alert->getAlertDetails());
            }

            throw new Exception(sprintf('Cannot upload file because the file is in the wrong format. Data sources can only have one type of file and format.', $origin_name));
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

    public function getDataSourceEntryToday(DataSourceInterface $dataSource, DateTime $dsNextTime)
    {
        return $this->repository->getDataSourceEntryForDataSourceByDate($dataSource, $dsNextTime);
    }
}