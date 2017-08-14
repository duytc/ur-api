<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\TagInterface;
use UR\Model\Core\UserTagInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\UserTagRepositoryInterface;

class UserTagManager implements UserTagManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, UserTagRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, UserTagInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $userTag)
    {
        if (!$userTag instanceof UserTagInterface) throw new InvalidArgumentException('expect UserTagInterface object');

        $this->om->persist($userTag);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $userTag)
    {
        if (!$userTag instanceof UserTagInterface) throw new InvalidArgumentException('expect UserTagInterface object');

        $this->om->remove($userTag);
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
    public function findByPublisher(PublisherInterface $publisher)
    {
        return $this->repository->findByPublisher($publisher);
    }

    /**
     * @inheritdoc
     */
    public function findByTag(TagInterface $tag)
    {
        return $this->repository->findByTag($tag);
    }
}