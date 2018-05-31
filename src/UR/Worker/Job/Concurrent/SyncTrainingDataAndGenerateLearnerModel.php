<?php


namespace UR\Worker\Job\Concurrent;


use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Exception\MissingJobParamException;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\OptimizationRuleManagerInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\OptimizationRule\AutomatedOptimization\AutomatedOptimizerInterface;
use UR\Service\OptimizationRule\OptimizationLearningFacadeServiceInterface;
use UR\Service\RestClientTrait;

class SyncTrainingDataAndGenerateLearnerModel implements JobInterface
{
    use  RestClientTrait;

    const JOB_NAME = 'syncTrainingDataAndGenerateLearnerModel';
    const OPTIMIZATION_RULE_ID_KEY = 'optimizationRuleId';

    /**
     * @var OptimizationRuleManagerInterface
     */
    private $optimizationRuleManager;

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
     * SyncTrainingDataAndGenerateLearnerModel constructor.
     * @param OptimizationRuleManagerInterface $optimizationRuleManager
     * @param LoggerInterface $logger
     * @param AutomatedOptimizerInterface $automatedOptimizer
     * @param OptimizationLearningFacadeServiceInterface $optimizationLearningFacadeService
     */
    public function __construct(OptimizationRuleManagerInterface $optimizationRuleManager,
                                AutomatedOptimizerInterface $automatedOptimizer,
                                OptimizationLearningFacadeServiceInterface $optimizationLearningFacadeService,
                                LoggerInterface $logger)
    {
        $this->optimizationRuleManager = $optimizationRuleManager;
        $this->logger = $logger;
        $this->automatedOptimizer = $automatedOptimizer;
        $this->optimizationLearningFacadeService = $optimizationLearningFacadeService;
    }

    public function getName(): string
    {
        return self::JOB_NAME;
    }

    /**
     * @param JobParams $params
     */
    public function run(JobParams $params)
    {
        try {
            $optimizationRuleId = (int)$params->getRequiredParam(self::OPTIMIZATION_RULE_ID_KEY);
        } catch (MissingJobParamException $e) {
            return;
        }

        $optimizationRule = $this->optimizationRuleManager->find($optimizationRuleId);
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return;
        }
        $this->optimizationLearningFacadeService->calculateNewScores($optimizationRule);

        // Notify new scores to integration platforms to update their data due to calculated scores
        try {
            $optimizationRule = $this->optimizationRuleManager->find($optimizationRuleId); //Note: Not remove this line
            if (!$optimizationRule instanceof OptimizationRuleInterface) {
                return;
            }

            $this->automatedOptimizer->optimizeForRule($optimizationRule);
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('There is an exception: %s for optimization rule %d', $e->getMessage(), $optimizationRule->getId()));
        }

        $this->logger->info(sprintf('Activate learning process successfully for optimization rule %d', $optimizationRule->getId()));
    }
}