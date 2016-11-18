<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use Liuggio\ExcelBundle\Factory;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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
        $detectedFields = $this->detectedFieldsForDataSource($dataSourceEntry, DataSourceEntryManager::DELETE);
        $dataSourceEntry->getDataSource()->setDetectedFields($detectedFields);
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
    public function uploadDataSourceEntryFiles($files, $path, $dirItem, $dataSource)
    {
        $result = [];
        /** @var  $files */
        $keys = $files->keys();
        foreach ($keys as $key) {
            /**@var UploadedFile $file */
            $file = $files->get($key);
            $origin_name = $file->getClientOriginalName();
            $file_name = basename($origin_name, '.' . $file->getClientOriginalExtension());
            $error = 0;

            if (strcmp(DataSourceType::DS_EXCEL_FORMAT, $dataSource->getFormat()) === 0) {
                if (!DataSourceType::isExcelType($file->getClientOriginalExtension())) {
                    $error = 1;
                }

            } else if (strcmp(DataSourceType::DS_CSV_FORMAT, $dataSource->getFormat()) === 0) {
                if (!DataSourceType::isCsvType($file->getClientOriginalExtension())) {
                    $error = 2;
                }

            } else if (strcmp(DataSourceType::DS_JSON_FORMAT, $dataSource->getFormat()) === 0) {
                if (!DataSourceType::isJsonType($file->getClientOriginalExtension())) {
                    $error = 3;
                }

            } else {
                $error = 4;
            }

            if ($error > 0) {
                if (in_array(DataSourceRepository::WRONG_FORMAT, $dataSource->getAlertSetting())) {
                    $code = ProcessAlert::NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT;
                    $publisherId = $dataSource->getPublisher()->getId();
                    $params = array(
                        ProcessAlert::FILE_NAME => $file_name . "." . $file->getClientOriginalExtension()
                    );
                    $this->workerManager->processAlert($code, $publisherId, $params);
                }
                throw new \Exception(sprintf("File %s is not valid", $origin_name));
            }

            // save file to upload dir
            $name = $file_name . '_' . round(microtime(true)) . '.' . $file->getClientOriginalExtension();
            $file->move($path, $name);

            // create new data source entry
            $dataSourceEntry = (new DataSourceEntry())
                ->setDataSource($dataSource)
                ->setPath($dirItem . '/' . $name)
                //->setValid() // set later by parser module
                //->setMetaData() // only for email...
                //->setReceivedDate() // auto
            ;

            $dataSourceEntry->setReceivedVia(DataSourceEntryInterface::RECEIVED_VIA_UPLOAD);
            $dataSourceEntry->setFileName($origin_name);
            $detectedFields = $this->detectedFieldsForDataSource($dataSourceEntry, DataSourceEntryManager::UPLOAD);
            $dataSourceEntry->getDataSource()->setDetectedFields($detectedFields);
            $this->save($dataSourceEntry);

            if (in_array(DataSourceRepository::DATA_RECEIVED, $dataSource->getAlertSetting())) {
                $code = ProcessAlert::NEW_DATA_IS_RECEIVED_FROM_UPLOAD;
                $publisherId = $dataSource->getPublisher()->getId();
                $params = array(
                    ProcessAlert::FILE_NAME => $file_name . "." . $file->getClientOriginalExtension(),
                    ProcessAlert::DATA_SOURCE_NAME => $dataSource->getName()
                );
                $this->workerManager->processAlert($code, $publisherId, $params);
            }
            $result[$origin_name] = 'success';

        }

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceEntryForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        return $this->repository->getDataSourceEntriesForPublisher($publisher, $limit, $offset);
    }

    public function detectedFieldsForDataSource(DataSourceEntryInterface $item, $option)
    {
        /**@var \UR\Service\DataSource\DataSourceInterface $file */
        /**@var DataSourceInterface $dataSource */
        $dataSource = $item->getDataSource();
        $detectedFields = $dataSource->getDetectedFields();
        $inputFile = $this->uploadFileDir . $item->getPath();

        if (strcmp($dataSource->getFormat(), 'csv') === 0) {
            /**@var Csv $file */
            $file = (new Csv($inputFile))->setDelimiter(',');
        } else if (strcmp($dataSource->getFormat(), 'excel') === 0) {
            /**@var Excel $file */
            $file = new \UR\Service\DataSource\Excel($inputFile, $this->phpExcel);
        } else {
            $file = new Json($item->getPath());
        }

        $columns = $file->getColumns();
        $newFields = [];
        $newFields = array_merge($newFields, $columns);
        $newFields = array_unique($newFields);
        $newFields = array_filter($newFields, function ($value) {
            return $value !== '';
        });

        foreach ($newFields as $newField) {
            $detectedFields = $this->updateDetectedField($newField, $detectedFields, $option);
        }
        return $detectedFields;
    }

    private function updateDetectedField($newField, $detectedFields, $option)
    {
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
}