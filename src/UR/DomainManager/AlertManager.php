<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\AlertInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\AlertRepositoryInterface;

class AlertManager implements AlertManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, AlertRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, AlertInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $alert)
    {
        if (!$alert instanceof AlertInterface) throw new InvalidArgumentException('expect AlertInterface object');

        $this->om->persist($alert);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $alert)
    {
        if (!$alert instanceof AlertInterface) throw new InvalidArgumentException('expect AlertInterface object');

        $this->om->remove($alert);
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
    public function deleteAlertsByIds($ids)
    {
        return $this->repository->deleteAlertsByIds($ids);
    }

    /**
     * @inheritdoc
     */
    public function updateMarkAsReadByIds($ids)
    {
        return $this->repository->updateMarkAsReadByIds($ids);
    }

    /**
     * @inheritdoc
     */
    public function updateMarkAsUnreadByIds($ids)
    {
        return $this->repository->updateMarkAsUnreadByIds($ids);
    }
}