<?php

namespace UR\DomainManager;

use DateTime;
use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Behaviors\FileUtilsTrait;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\DataSourceEntryRepositoryInterface;

class DataSourceEntryManager implements DataSourceEntryManagerInterface
{
    use FileUtilsTrait;

    /** @var ObjectManager */
    protected $om;
    /** @var DataSourceEntryRepositoryInterface */
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
    public function isFileAlreadyImported(DataSourceInterface $dataSource, $hash)
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

    /**
     * @inheritdoc
     */
    public function findByDateRange(DataSourceInterface $dataSource, DateTime $startDate, DateTime $endDate)
    {
        return $this->repository->findByDateRange($dataSource, $startDate, $endDate);
    }

    /**
     * @inheritdoc
     */
    public function getCleanUpEntries(DataSourceInterface $dataSource)
    {
        return $this->repository->getCleanUpEntries($dataSource);
    }
}