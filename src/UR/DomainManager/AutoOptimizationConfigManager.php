<?php


namespace UR\DomainManager;


use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use ReflectionClass;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\AutoOptimizationConfigRepositoryInterface;

class AutoOptimizationConfigManager implements AutoOptimizationConfigManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, AutoOptimizationConfigRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, AutoOptimizationConfigInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $entity)
    {
        if (!$entity instanceof AutoOptimizationConfigInterface) {
            throw  new InvalidArgumentException('expect AutoOptimizationConfig object');
        }

        try {
            $this->om->persist($entity);
            $this->om->flush();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $entity)
    {
        if (!$entity instanceof AutoOptimizationConfigInterface) {
            throw new InvalidArgumentException('expect AutoOptimizationConfigInterface object');
        }

        $this->om->remove($entity);
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
    public function findByPublisher(PublisherInterface $publisher)
    {
        return $this->repository->findByPublisher($publisher);
    }
    /**
     * @inheritdoc
     */
    public function all($limit = null, $offset = null)
    {
        return $this->repository->findBy($criteria = [], $orderBy = null, $limit, $offset);
    }
}