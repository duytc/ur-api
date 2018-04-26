<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\ModelInterface;
use UR\Model\PagerParam;
use UR\Repository\Core\OptimizationIntegrationRepositoryInterface;

class OptimizationIntegrationManager implements OptimizationIntegrationManagerInterface
{
    /**
     * @var ObjectManager
     */
    private $om;
    /**
     * @var OptimizationIntegrationRepositoryInterface
     */
    private $optimizationIntegrationRepository;

    /**
     * OptimizationRuleManager constructor.
     * @param ObjectManager $om
     * @param OptimizationIntegrationRepositoryInterface $optimizationIntegrationRepository
     */
    public function __construct(ObjectManager $om, OptimizationIntegrationRepositoryInterface $optimizationIntegrationRepository)
    {
        $this->om = $om;
        $this->optimizationIntegrationRepository = $optimizationIntegrationRepository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, OptimizationIntegrationInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $entity)
    {
        if (!$entity instanceof OptimizationIntegrationInterface) {
            throw new InvalidArgumentException('expect OptimizationIntegrationInterface object');
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
        if (!$entity instanceof OptimizationIntegrationInterface) {
            throw new InvalidArgumentException('expect OptimizationIntegrationInterface object');
        }

        $this->om->remove($entity);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function createNew()
    {
        $entity = new ReflectionClass($this->optimizationIntegrationRepository->getClassName());

        return $entity->newInstance();
    }

    /**
     * @inheritdoc
     */
    public function find($id)
    {
        return $this->optimizationIntegrationRepository->find($id);
    }

    /**
     * @inheritdoc
     */
    public function all($limit = null, $offset = null)
    {
        return $this->optimizationIntegrationRepository->findBy($criteria = [], $orderBy = null, $limit, $offset);
    }

    /**
     * @inheritdoc
     */
    public function getOptimizationIntegrationAdSlotIds($optimizationIntegrationId = null)
    {
        return $this->optimizationIntegrationRepository->getOptimizationIntegrationAdSlotIds($optimizationIntegrationId);
    }
}