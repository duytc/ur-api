<?php

namespace Tagcade\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use Tagcade\Exception\InvalidArgumentException;
use Tagcade\Model\Core\DataSourceInterface;
use Tagcade\Model\ModelInterface;
use Tagcade\Model\User\Role\PublisherInterface;
use Tagcade\Repository\Core\DataSourceRepositoryInterface;
use ReflectionClass;

class DataSourceManager implements DataSourceManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, DataSourceRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        return $this->repository->getDataSourcesForPublisher($publisher, $limit, $offset);
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, DataSourceInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $dataSource)
    {
        if (!$dataSource instanceof DataSourceInterface) throw new InvalidArgumentException('expect DataSourceInterface Object');
        $this->om->persist($dataSource);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $dataSource)
    {
        if (!$dataSource instanceof DataSourceInterface) throw new InvalidArgumentException('expect DataSourceInterface Object');
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
}