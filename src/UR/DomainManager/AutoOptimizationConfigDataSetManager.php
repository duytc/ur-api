<?php


namespace UR\DomainManager;


use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Instantiator\Exception\InvalidArgumentException;
use ReflectionClass;
use UR\Model\Core\AutoOptimizationConfigDataSetInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\AutoOptimizationConfigDataSetRepositoryInterface;

class AutoOptimizationConfigDataSetManager implements AutoOptimizationConfigDataSetManagerInterface
{

    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, AutoOptimizationConfigDataSetRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, AutoOptimizationConfigDataSetInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $entity)
    {
        if (!$entity instanceof AutoOptimizationConfigDataSetInterface) {
            throw new InvalidArgumentException('expect AutoOptimizationConfigDataSetInterface object');
        }

        try {
            $this->om->persist($entity);
            $this->om->flush();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * @param ModelInterface $entity
     * @return void
     */
    public function delete(ModelInterface $entity)
    {
        if (!$entity instanceof AutoOptimizationConfigDataSetInterface) {
            throw new InvalidArgumentException('expect AutoOptimizationConfigDataSetInterface object');
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
    public function all($limit = null, $offset = null)
    {
        return $this->repository->findBy($criteria = [], $orderBy = null, $limit, $offset);
    }
}