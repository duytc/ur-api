<?php


namespace UR\Worker\Job\Concurrent;


use Exception;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Exception\MissingJobParamException;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\DomainManager\OptimizationIntegrationManagerInterface;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Service\OptimizationRule\AutomatedOptimization\AutomatedOptimizerInterface;
use UR\Service\OptimizationRule\OptimizationLearningFacadeServiceInterface;
use UR\Service\RestClientTrait;

class OptimizeOptimizationIntegrationNow implements JobInterface
{
    use  RestClientTrait;

    const JOB_NAME = 'optimizeOptimizationIntegrationNow';
    const OPTIMIZATION_INTEGRATION_ID_KEY = 'optimizationIntegrationId';
    /**
     * @var LoggerInterface
     */
    private $logger;

    /** @var AutomatedOptimizerInterface */
    private $automatedOptimizer;

    /**
     * @var OptimizationLearningFacadeServiceInterface
     */
    private $optimizationLearningFacadeService;
    /**
     * @var OptimizationIntegrationManagerInterface
     */
    private $optimizationIntegrationManager;

    /**
     * OptimizeOptimizationIntegrationNow constructor.
     * @param OptimizationIntegrationManagerInterface $optimizationIntegrationManager
     * @param AutomatedOptimizerInterface $automatedOptimizer
     * @param OptimizationLearningFacadeServiceInterface $optimizationLearningFacadeService
     * @param LoggerInterface $logger
     */
    public function __construct(OptimizationIntegrationManagerInterface $optimizationIntegrationManager,
                                AutomatedOptimizerInterface $automatedOptimizer,
                                OptimizationLearningFacadeServiceInterface $optimizationLearningFacadeService,
                                LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->automatedOptimizer = $automatedOptimizer;
        $this->optimizationLearningFacadeService = $optimizationLearningFacadeService;
        $this->optimizationIntegrationManager = $optimizationIntegrationManager;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    /**
     * @param JobParams $params
     * @return mixed
     * @throws Exception
     */
    public function run(JobParams $params)
    {
        try {
            $optimizeIntegrationId = (int)$params->getRequiredParam(self::OPTIMIZATION_INTEGRATION_ID_KEY);
        } catch (MissingJobParamException $e) {
            return false;
        }

        $optimizeIntegration = $this->optimizationIntegrationManager->find($optimizeIntegrationId);
        if (!$optimizeIntegration instanceof OptimizationIntegrationInterface) {
            throw  new Exception(sprintf('Cannot find the integration, id =%d', $optimizeIntegrationId));
        }

        $result = $this->optimizationLearningFacadeService->calculateNewScores($optimizeIntegration->getOptimizationRule());
        if (!$result) {
            throw  new Exception(sprintf('There is a error when optimizing for integration rule, id =%d', $optimizeIntegrationId));
        }

        $optimizeIntegration = $this->optimizationIntegrationManager->find($optimizeIntegrationId);
        if (!$optimizeIntegration instanceof OptimizationIntegrationInterface) {
            throw  new Exception(sprintf('Cannot find the integration, id =%d', $optimizeIntegrationId));
        }
        $oldFrequencySetting = $optimizeIntegration->getOptimizationFrequency();
        $optimizeIntegration->setOptimizationFrequency(DateFilter::DATETIME_DYNAMIC_VALUE_CONTINUOUSLY);

        try {
            $optimizeResult = $this->automatedOptimizer->optimizeForRule($optimizeIntegration->getOptimizationRule(), [$optimizeIntegrationId]);
        } catch (Exception $e) {
            throw  $e;
        }

        if (!$optimizeResult) {
            throw  new Exception(sprintf('There is a error when optimizing for integration, id =%d', $optimizeIntegration->getId()));
        }

        $optimizeIntegration = $this->optimizationIntegrationManager->find($optimizeIntegrationId);
        if (!$optimizeIntegration instanceof OptimizationIntegrationInterface) {
            throw  new Exception(sprintf('Cannot find the integration, id =%d', $optimizeIntegrationId));
        }

        $optimizeIntegration->setOptimizationFrequency($oldFrequencySetting);
        $this->optimizationIntegrationManager->save($optimizeIntegration);

        return $optimizeResult;
    }
}