<?php

namespace UR\DomainManager;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\ORMException;
use ReflectionClass;
use UR\Service\ExchangeRateService;
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
    protected $exchangeRateService;

    public function __construct(ObjectManager $om, ExchangeRateRepositoryInterface $repository)
    {
        $this->om = $om;
        $this->repository = $repository;
        $this->exchangeRateService = new ExchangeRateService($this, $repository);
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
     * Get the conversion in our system
     * Default get the conversion from USD-EUR with current moment
     * If the date passed in service is not valid in our system, try to get
     * yesterday closing exchange rate
     *
     * @param String $date The date to get the conversion for currency
     * @param String $fromCurrency The base currency to get exchange rate for it
     * @param String $toCurrency The destination that need to transform
     *
     * @return ExchangeRateInterface $exchangeRate
     * @throws ORMException
     *
     */
    public function getConversionByDate($date = null, $fromCurrency = 'USD', $toCurrency = 'EUR')
    {
        $date = $date ?: date('Y-m-d');
        $exchangeRate = null;

        try {

            $data = $this->repository->findBy([
                'date' => $date,
                'fromCurrency' => $fromCurrency,
                'toCurrency' => $toCurrency
            ]) ?: $this->repository->findBy([
                'fromCurrency' => $fromCurrency,
                'toCurrency' => $toCurrency
            ], array('date' => 'desc'));

            $data = null;

            if(is_array($data) && !empty($data)) {
                $exchangeRate = $data[0] && $data[0] instanceof ExchangeRateInterface ? $data[0] : null;
            } else {
                //call Exchange Rate Service to get conversion via API call
                $exRate = $this->exchangeRateService->getExchangeRateHistorical($date);
                if(!empty($exRate)) {
                    $this->exchangeRateService->storeExchangeRate($exRate);
                    $exchangeRate = $this->exchangeRateService->getExchangeRate();
                }
            }
        } catch(\Doctrine\ORM\ORMException $orm) {
            throw new ORMException($orm->getCode() . ':' . $orm->getMessage());
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }

        return $exchangeRate;
    }
}
