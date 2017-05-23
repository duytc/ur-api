<?php

namespace UR\DomainManager;

use DateTime;
use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use UR\Behaviors\FileUtilsTrait;
use UR\Entity\Core\DataSourceEntry;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\DataSourceEntryRepositoryInterface;
use UR\Service\Alert\DataSource\AbstractDataSourceAlert;
use UR\Service\Alert\DataSource\DataReceivedAlert;
use UR\Service\Alert\DataSource\DataSourceAlertFactory;
use UR\Service\DataSource\DataSourceType;
use UR\Service\Import\ImportDataException;
use UR\Service\Import\ImportService;
use UR\Worker\Manager;

class DataSourceEntryManager implements DataSourceEntryManagerInterface
{
    use FileUtilsTrait;

    /** @var ObjectManager */
    protected $om;
    /** @var DataSourceEntryRepositoryInterface */
    protected $repository;
    /** @var Manager */
    private $workerManager;
    /** @var ImportService */
    private $importService;
    /** @var DataSourceAlertFactory */
    private $alertFactory;

    public function __construct(ObjectManager $om, DataSourceEntryRepositoryInterface $repository, Manager $workerManager, ImportService $importService)
    {
        $this->om = $om;
        $this->repository = $repository;
        $this->workerManager = $workerManager;
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
        // sure entity is a DataSourceEntry
        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            throw new InvalidArgumentException('expect DataSourceEntryInterface Object');
        }

        // remove
        $this->om->remove($dataSourceEntry);
        $this->om->flush();
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

        $originName = $file->getClientOriginalName();
        // escape $filename (remove special characters)
        $originName = $this->escapeFileNameContainsSpecialCharacters($originName);

        $filename = basename($originName, '.' . $file->getClientOriginalExtension());

        $publisherId = $dataSource->getPublisher()->getId();

        try {
            // validate file extension before processing upload
            $isExtensionSupport = DataSourceType::isSupportedExtension($file->getClientOriginalExtension());
            if (!$isExtensionSupport) {
                throw new ImportDataException(AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT);
            }

            // automatically update data source format if has no entry before
            if (count($dataSource->getDataSourceEntries()) < 1) {
                $rawFileFormat = $file->getClientOriginalExtension();
                $format = DataSourceType::getOriginalDataSourceType($rawFileFormat);
                $dataSource->setFormat($format);
                $this->om->persist($dataSource);
                $this->om->flush();
            }

            $isValidExtension = $this->importService->validateUploadedFile($file, $dataSource);
            if (!$isValidExtension) {
                throw new ImportDataException(AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT);
            }

            // save file to upload dir
            if (DataSourceEntry::RECEIVED_VIA_API === $receivedVia) {
                $name = $originName;
            } else {
                $name = $filename . '_' . round(microtime(true)) . '.' . $file->getClientOriginalExtension();
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

            chmod($filePath, 0664);

            $convertResult = $this->convertToUtf8($filePath, $this->importService->getKernelRootDir());
            if (!$convertResult) {
                throw new \Exception(sprintf("File %s is not valid - cannot convert to UTF-8", $originName));
            }

            $hash = sha1_file($filePath);
            if ($this->isFileAlreadyImported($dataSource, $hash)) {
                throw new Exception(sprintf('File "%s" is already imported', $originName));
            }

            // create new data source entry
            $dataSourceEntry = new DataSourceEntry();
            $dataSourceEntry->setPath($dirItem . '/' . $name)
                ->setIsValid(true)
                ->setReceivedVia($receivedVia)
                ->setFileName($originName)
                ->setHashFile($hash)
                ->setMetaData($metadata)
                ->setDataSource($dataSource);

            $this->save($dataSourceEntry);

            $alert = $this->alertFactory->getAlert(
                AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD,
                $originName,
                $dataSource);

            if ($alert instanceof DataReceivedAlert) {
                $this->workerManager->processAlert($alert->getAlertCode(), $publisherId, $alert->getDetails());
            }

        } catch (ImportDataException $exception) {
            $code = $exception->getAlertCode();
            $alert = $this->alertFactory->getAlert(
                $code,
                $originName,
                $dataSource);

            if ($alert instanceof AbstractDataSourceAlert) {
                $this->workerManager->processAlert($alert->getAlertCode(), $publisherId, $alert->getDetails());
            }

            throw new Exception(sprintf('Cannot upload file because the file is in the wrong format. Data sources can only have one type of file and format.', $originName));
        }

        $result = [
            'file' => $originName,
            'status' => true,
            'message' => sprintf('File %s is uploaded successfully', $originName)
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

    /**
     * @inheritdoc
     */
    public function getDataSourceEntryToday(DataSourceInterface $dataSource, DateTime $dsNextTime)
    {
        return $this->repository->getDataSourceEntryForDataSourceByDate($dataSource, $dsNextTime);
    }

    /**
     * check if file is Already Imported
     *
     * @param DataSourceInterface $dataSource
     * @param $hash
     * @return bool
     */
    private function isFileAlreadyImported(DataSourceInterface $dataSource, $hash)
    {
        $importedFiles = $this->repository->getImportedFileByHash($dataSource, $hash);
        return is_array($importedFiles) && count($importedFiles) > 0;
    }

    /**
     * @inheritdoc
     */
    public function findByDataSource($dataSource, $limit = null, $offset = null)
    {
        return $this->repository->getDataSourceEntriesForDataSource($dataSource, $limit, $offset);
    }
}