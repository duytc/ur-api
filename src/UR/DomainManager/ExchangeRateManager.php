<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;

use ReflectionClass;
use UR\Service\RestClientTrait;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ExchangeRateInterface;
use UR\Model\ModelInterface;
use UR\Repository\Core\ExchangeRateRepositoryInterface;

class ExchangeRateManager implements ExchangeRateManagerInterface
{
    use RestClientTrait;

    protected $om;
    protected $repository;
    protected $commandAPI;

    public function __construct(ObjectManager $om, ExchangeRateRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
    }

    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, ExchangeRateInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $exchangeRate)
    {
        if (!$exchangeRate instanceof ExchangeRateInterface) throw new InvalidArgumentException('expect ExchangeRateInterface object');

        $this->om->persist($exchangeRate);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function delete(ModelInterface $exchangeRate)
    {
        if (!$exchangeRate instanceof ExchangeRateInterface) throw new InvalidArgumentException('expect ExchangeRateInterface object');

        $this->om->remove($exchangeRate);
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
    public function findBy($criteria = []) {
        return $this->repository->findBy($criteria);
    }
}
