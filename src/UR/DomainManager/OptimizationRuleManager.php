<?php


namespace UR\DomainManager;


use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\DBAL\Types\Type;
use ReflectionClass;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Model\Core\ReportViewInterface;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Repository\Core\OptimizationIntegrationRepositoryInterface;
use UR\Repository\Core\OptimizationRuleRepositoryInterface;

class OptimizationRuleManager implements OptimizationRuleManagerInterface
{
    const FIELD_KEY = 'field';
    /**
     * @var ObjectManager
     */
    private $om;
    /**
     * @var OptimizationRuleRepositoryInterface
     */
    private $optimizationRuleRepository;

    /**
     * @var OptimizationIntegrationRepositoryInterface
     */
    private $optimizationIntegrationRepository;

    /**
     * OptimizationRuleManager constructor.
     * @param ObjectManager $om
     * @param OptimizationRuleRepositoryInterface $optimizationRuleRepository
     * @param OptimizationIntegrationRepositoryInterface $optimizationIntegrationRepository
     */
    public function __construct(ObjectManager $om, OptimizationRuleRepositoryInterface $optimizationRuleRepository, OptimizationIntegrationRepositoryInterface $optimizationIntegrationRepository)
    {
        $this->om = $om;
        $this->optimizationRuleRepository = $optimizationRuleRepository;
        $this->optimizationIntegrationRepository = $optimizationIntegrationRepository;
    }


    /**
     * @inheritdoc
     */
    public function supportsEntity($entity)
    {
        return is_subclass_of($entity, OptimizationRuleInterface::class);
    }

    /**
     * @inheritdoc
     */
    public function save(ModelInterface $entity)
    {
        if (!$entity instanceof OptimizationRuleInterface) {
            throw new InvalidArgumentException('Expect OptimizationRule object.');
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
        if (!$entity instanceof OptimizationRuleInterface) {
            throw new InvalidArgumentException('Expect OptimizationRule object.');
        }

        if ($this->optimizationIntegrationRepository->hasOptimizationIntegrations($entity)) {
            throw new InvalidArgumentException("Can not delete this optimization rule because there is some Ad Slot were assigned to this.");
        }

        $this->om->remove($entity);
        $this->om->flush();
    }

    /**
     * @inheritdoc
     */
    public function createNew()
    {
        $entity = new ReflectionClass($this->optimizationRuleRepository->getClassName());

        return $entity->newInstance();
    }

    /**
     * @inheritdoc
     */
    public function find($id)
    {
        return $this->optimizationRuleRepository->find($id);
    }

    /**
     * @inheritdoc
     */
    public function all($limit = null, $offset = null)
    {
        return $this->optimizationRuleRepository->findBy($criteria = [], $orderBy = null, $limit, $offset);
    }

    /**
     * @inheritdoc
     */
    public function getOptimizationRulesForPublisher(PublisherInterface $publisher, $limit = null, $offset = null)
    {
        return $this->optimizationRuleRepository->getOptimizationRulesForPublisher($publisher, $limit, $offset);
    }

    public function getOptimizeFieldName(ModelInterface $optimizationRule)
    {
        $optimizeFieldNames = [];
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
           return $optimizeFieldNames;
        }

        $optimizeFields = $optimizationRule->getOptimizeFields();
        $reportView = $optimizationRule->getReportView();
        if (!$reportView instanceof  ReportViewInterface) {
            return $optimizeFieldNames;
        }
        $types = $reportView->getFieldTypes();

        if(empty($optimizeFields)) {
            return $optimizeFields;
        }

        foreach ($optimizeFields as $optimizeField) {
            if(!array_key_exists(self::FIELD_KEY, $optimizeField)) {
                continue;
            }

            $fieldName = $optimizeField[self::FIELD_KEY];
            if (array_key_exists($fieldName, $types)) {
                $type = $types[$optimizeField[self::FIELD_KEY]];
            } else {
                $type = Type::DECIMAL;
            }
            $optimizeFieldNames[$fieldName]  = $type;
        }

        return $optimizeFieldNames;
    }
}