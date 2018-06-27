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
            $this->logger->warning(sprintf('Cannot find the integration, id =%d', $optimizeIntegrationId));
        }

        $result = $this->optimizationLearningFacadeService->calculateNewScores($optimizeIntegration->getOptimizationRule());
        if (!$result) {
            $this->logger->warning(sprintf('There is a error when optimizing for integration rule, id =%d', $optimizeIntegrationId));

            return false;
        }

        $optimizeIntegration = $this->optimizationIntegrationManager->find($optimizeIntegrationId);
        if (!$optimizeIntegration instanceof OptimizationIntegrationInterface) {
            $this->logger->warning(sprintf('Cannot find the integration, id =%d', $optimizeIntegrationId));

            return false;
        }
        $oldFrequencySetting = $optimizeIntegration->getOptimizationFrequency();
        $optimizeIntegration->setOptimizationFrequency(DateFilter::DATETIME_DYNAMIC_VALUE_CONTINUOUSLY);

        //Tip to force optimize run after change
        $startDate = time();
        $endDate = strtotime('-1 minutes', $startDate);
        $startDate = date_create_from_format('Y-m-d H:i:s', date('Y-m-d H:i:s', $startDate));
        $endDate = date_create_from_format('Y-m-d H:i:s', date('Y-m-d H:i:s', $endDate));

        $optimizeIntegration->setStartRescoreAt($startDate);
        $optimizeIntegration->setEndRescoreAt($endDate);

        try {
            $optimizeResult = $this->automatedOptimizer->optimizeForRule($optimizeIntegration->getOptimizationRule(), [$optimizeIntegrationId]);
        } catch (Exception $e) {
            $this->logger->warning(sprintf(sprintf('There is an exception: %s for optimization rule %d', $e->getMessage(), $optimizeIntegration->getOptimizationRule()->getId())));

            return false;
        }

        if (!$optimizeResult) {
            $this->logger->warning(sprintf('There is a error when optimizing for integration, id =%d', $optimizeIntegration->getId()));

            return false;
        }

        $optimizeIntegration = $this->optimizationIntegrationManager->find($optimizeIntegrationId);
        if (!$optimizeIntegration instanceof OptimizationIntegrationInterface) {
            $this->logger->warning(sprintf('Cannot find the integration, id =%d', $optimizeIntegrationId));

            return false;
        }

        $optimizeIntegration->setOptimizationFrequency($oldFrequencySetting);
        $this->optimizationIntegrationManager->save($optimizeIntegration);

        return $optimizeResult;
    }
}