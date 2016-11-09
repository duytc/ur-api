<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use UR\Entity\Core\Alert;
use UR\Entity\Core\DataSourceEntry;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\DataSourceEntryRepository;
use UR\Repository\Core\DataSourceEntryRepositoryInterface;
use ReflectionClass;
use UR\Repository\Core\DataSourceRepository;

class DataSourceEntryManager implements DataSourceEntryManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, DataSourceEntryRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
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
    public function save(ModelInterface $dataSource)
    {
        if (!$dataSource instanceof DataSourceEntryInterface) throw new InvalidArgumentException('expect DataSourceEntryInterface Object');
        $this->om->persist($dataSource);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $dataSource)
    {
        if (!$dataSource instanceof DataSourceEntryInterface) throw new InvalidArgumentException('expect DataSourceEntryInterface Object');
        $this->om->remove($dataSource);
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

            if (($file->getClientOriginalExtension() === $dataSource->getFormat()) || ($dataSource->getFormat() === DataSourceEntryRepository::EXCEL_FORMAT && in_array($file->getClientOriginalExtension(), DataSourceEntryRepository::$excelType))) {
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
                $this->save($dataSourceEntry);

                $alertSetting = $dataSource->getAlertSetting();
                if (in_array(DataSourceRepository::DATA_RECEIVED, $alertSetting)) {
                    $alert = new Alert();
                    $alert->setDataSourceEntry($dataSourceEntry);
                    $alert->setTitle(sprintf('Upload file successfully'));
                    $alert->setType(sprintf('info'));
                    $alert->setMessage(sprintf('File %s of %s is uploaded', $file_name . "." . $file->getClientOriginalExtension(), $dataSourceEntry->getDataSource()->getName()));
                    $this->om->persist($alert);
                    $this->om->flush();
                }

                $result[$origin_name] = 'success';
            } else {
                $alertSetting = $dataSource->getAlertSetting();
                if (in_array(DataSourceRepository::WRONG_FORMAT, $alertSetting)) {
                    $alert = new Alert();
                    $alert->setTitle(sprintf('Upload file failure'));
                    $alert->setType(sprintf('error'));
                    $alert->setMessage(sprintf('File %s is not uploaded', $file_name . "." . $file->getClientOriginalExtension()));
                    $this->om->persist($alert);
                    $this->om->flush();
                }

                throw new \Exception(sprintf("File %s is not valid", $origin_name));
            }
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
}