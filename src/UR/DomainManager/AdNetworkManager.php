<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\AdNetworkInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\AdNetworkRepositoryInterface;

class AdNetworkManager implements AdNetworkManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, AdNetworkRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, AdNetworkInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $adNetwork)
    {
        if (!$adNetwork instanceof AdNetworkInterface) throw new InvalidArgumentException('expect AdNetworkInterface object');

        $this->om->persist($adNetwork);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $adNetwork)
    {
        if (!$adNetwork instanceof AdNetworkInterface) throw new InvalidArgumentException('expect AdNetworkInterface object');

        $this->om->remove($adNetwork);
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
    public function getAdNetworksForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        return $this->repository->getAdNetworksForPublisher($publisher, $limit, $offset);
    }
}