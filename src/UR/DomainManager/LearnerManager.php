<?php


namespace UR\DomainManager;


use Doctrine\Common\Persistence\ObjectManager;
use Prophecy\Exception\InvalidArgumentException;
use ReflectionClass;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\LearnerInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\LearnerRepositoryInterface;

class LearnerManager implements LearnerManagerInterface
{
    /**
     * @var ObjectManager
     */
    private $om;
    /**
     * @var LearnerRepositoryInterface
     */
    private $repository;

    public function __construct(ObjectManager $om, LearnerRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * Should take an object instance or string class name
     * Should return true if the supplied entity object or class is supported by this manager
     *
     * @param ModelInterface|string $entity
     * @return bool
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, LearnerInterface::class);
    }

    /**
     * @param ModelInterface $learner
     */

    public function save(ModelInterface $learner)
    {
        if (!$learner instanceof LearnerInterface) throw new InvalidArgumentException('expect LearnerInterface Object');
        $this->om->persist($learner);
        $this->om->flush();
    }

    /**
     * @param ModelInterface $learner
     */

    public function delete(ModelInterface $learner)
    {
        if (!$learner instanceof LearnerInterface) throw new InvalidArgumentException('expect LearnerInterface Object');
        $this->om->remove($learner);
        $this->om->flush();
    }

    /**
     * @return ModelInterface
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
    public function getLearnerByParams(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier, $type)
    {
        /** @var LearnerInterface $learner */
        $learners = $this->repository->getLearnerByParams($autoOptimizationConfig, $identifier);

        $learner = array_shift($learners);
        if (!$learner instanceof LearnerInterface) {
            return [];
        }

        return $learner;
    }
}