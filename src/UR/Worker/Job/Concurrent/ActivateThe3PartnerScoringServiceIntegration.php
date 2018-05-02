<?php


namespace UR\Worker\Job\Concurrent;


use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Exception\MissingJobParamException;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\DomainManager\OptimizationRuleManagerInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\OptimizationRule\AutomatedOptimization\AutomatedOptimizerInterface;

class ActivateThe3PartnerScoringServiceIntegration implements JobInterface
{
    const JOB_NAME = 'ActivateThe3PartnerScoringServiceIntegration';
    const OPTIMIZATION_RULE_ID_KEY = 'optimizationRuleId';
    const OPTIMIZATION_INTEGRATION_ID_KEY = 'optimizationIntegrationId';
    /**
     * @var AutomatedOptimizerInterface
     */
    private $automatedOptimizer;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var OptimizationRuleManagerInterface
     */
    private $optimizationRuleManager;

    /**
     * ActivateThe3PartnerScoringServiceIntegration constructor.
     * @param AutomatedOptimizerInterface $automatedOptimizer
     * @param OptimizationRuleManagerInterface $optimizationRuleManager
     * @param LoggerInterface $logger
     */
    public function __construct(AutomatedOptimizerInterface $automatedOptimizer, OptimizationRuleManagerInterface $optimizationRuleManager,
                                LoggerInterface $logger)
    {
        $this->automatedOptimizer = $automatedOptimizer;
        $this->logger = $logger;
        $this->optimizationRuleManager = $optimizationRuleManager;
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
            $optimizationRuleId = $params->getRequiredParam(self::OPTIMIZATION_RULE_ID_KEY);
            $optimizationIntegrationId = $params->getRequiredParam(self::OPTIMIZATION_INTEGRATION_ID_KEY);
        } catch (MissingJobParamException $e) {
            return;
        }

        $optimizationRule = $this->optimizationRuleManager->find($optimizationRuleId);

        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            return;
        }
        try {
            $this->automatedOptimizer->optimizeForRule($optimizationRule, [$optimizationIntegrationId]);
        } catch (\Exception $e) {
            $this->logger->warning(sprintf('There is an exception: %s for optimization rule %d', $e->getMessage(), $optimizationRule->getId()));
        }

        $this->logger->info(sprintf('Activate the 3rd integration successfully for optimization rule %d and optimization integration', $optimizationRule->getId(), $optimizationIntegrationId));
    }
}