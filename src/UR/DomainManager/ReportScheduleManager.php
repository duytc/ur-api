<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportScheduleInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\ReportScheduleRepositoryInterface;

class ReportScheduleManager implements ReportScheduleManagerInterface
{
    protected $om;
    protected $repository;

    public function __construct(ObjectManager $om, ReportScheduleRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, ReportScheduleInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $alert)
    {
        if (!$alert instanceof ReportScheduleInterface) throw new InvalidArgumentException('expect ReportScheduleInterface object');

        $this->om->persist($alert);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $alert)
    {
        if (!$alert instanceof ReportScheduleInterface) throw new InvalidArgumentException('expect ReportScheduleInterface object');

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
}