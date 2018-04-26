<?php


namespace UR\DomainManager;


use Doctrine\Common\Persistence\ObjectManager;
use InvalidArgumentException;
use ReflectionClass;
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
    private $learnerRepository;

    public function __construct(ObjectManager $om, LearnerRepositoryInterface $learnerRepository)
    {
        $this->om = $om;
        $this->learnerRepository = $learnerRepository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, LearnerInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $entity)
    {
        if (!$entity instanceof LearnerInterface) {
            throw new InvalidArgumentException('expect learner object');
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
        if (!$entity instanceof LearnerInterface) {
            throw new InvalidArgumentException('expect learner object');
        }

        $this->om->remove($entity);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function createNew()
    {
        $entity = new ReflectionClass($this->learnerRepository->getClassName());

        return $entity->newInstance();
    }

    /**
     * @inheritdoc
     */
    public function find($id)
    {
        return $this->learnerRepository->find($id);
    }

    /**
     * @inheritdoc
     */
    public function all($limit = null, $offset = null)
    {
        return $this->learnerRepository->findBy($criteria = [], $orderBy = null, $limit, $offset);
    }

}