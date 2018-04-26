<?php

namespace UR\Worker\Job\Concurrent;

use DateTime;
use Exception;
use Psr\Log\LoggerInterface;
use Pubvantage\Worker\Job\JobInterface;
use Pubvantage\Worker\JobParams;
use UR\Behaviors\OptimizationRuleUtilTrait;
use UR\DomainManager\OptimizationIntegrationManagerInterface;
use UR\DomainManager\OptimizationRuleManagerInterface;
use UR\Model\Core\OptimizationIntegrationInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\OptimizationRule\AutomatedOptimization\AutomatedOptimizerInterface;
use UR\Service\OptimizationRule\OptimizationLearningFacadeServiceInterface;

class ProcessOptimizationFrequency implements JobInterface
{
    use OptimizationRuleUtilTrait;
    
    const JOB_NAME = 'process_optimization_frequency';

    /** @var OptimizationIntegrationManagerInterface */
    private $optimizationIntegrationManager;

    /** @var AutomatedOptimizerInterface */
    private $automatedOptimizer;

    /** @var OptimizationRuleManagerInterface */
    private $optimizationRuleManager;

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var OptimizationLearningFacadeServiceInterface
     */
    private $optimizationLearningFacadeService;

    /**
     * ProcessOptimizationFrequency constructor.
     * @param OptimizationIntegrationManagerInterface $optimizationIntegrationManager
     * @param AutomatedOptimizerInterface $automatedOptimizer
     * @param OptimizationRuleManagerInterface $optimizationRuleManager
     * @param OptimizationLearningFacadeServiceInterface $optimizationLearningFacadeService
     * @param LoggerInterface $logger
     */
    public function __construct(OptimizationIntegrationManagerInterface $optimizationIntegrationManager, AutomatedOptimizerInterface $automatedOptimizer,
                                OptimizationRuleManagerInterface $optimizationRuleManager, OptimizationLearningFacadeServiceInterface $optimizationLearningFacadeService,
                                LoggerInterface $logger)
    {
        $this->optimizationIntegrationManager = $optimizationIntegrationManager;
        $this->automatedOptimizer = $automatedOptimizer;
        $this->optimizationRuleManager = $optimizationRuleManager;
        $this->optimizationLearningFacadeService = $optimizationLearningFacadeService;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function getName(): string
    {
        return self::JOB_NAME;
    }

    /**
     * @inheritdoc
     */
    public function run(JobParams $params)
    {

        $optimizationRules = $this->getOptimizationRulesToReCalculatedScores();
        foreach ($optimizationRules as $optimizationRule) {
            $this->optimizationLearningFacadeService->calculateNewScores($optimizationRule);
        }

        $optimizationIntegrations = $this->optimizationIntegrationManager->all();
        $optimizationRules = [];

        foreach ($optimizationIntegrations as $optimizationIntegration) {
            if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                continue;
            }
            if (!$this->isOutOfDate($optimizationIntegration)) {
                continue;
            }

            $optimizationRule = $optimizationIntegration->getOptimizationRule();
            $optimizationRules[$optimizationRule->getId()][] = $optimizationIntegration;
        }

        foreach ($optimizationRules as $ruleId => $optimizationIntegrations) {
            $optimizationRule = $this->optimizationRuleManager->find($ruleId);
            if (!$optimizationRule instanceof OptimizationRuleInterface) {
                continue;
            }

            $this->updateStartDate($optimizationIntegrations);
            try {
                foreach ($optimizationIntegrations as $optimizationIntegration) {
                    if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                        continue;
                    }
                    $optimizationIntegrationIds = [$optimizationIntegration->getId()];
                    try {
                        $this->automatedOptimizer->optimizeForRule($optimizationRule, $optimizationIntegrationIds);
                    } catch (\Exception $e) {
                        $this->logger->warning(sprintf('There is an exception: %s for optimization rule %d', $e->getMessage(), $optimizationRule->getId()));
                    }
                }
            } catch (Exception $e) {
                throw $e;
            }
            $this->updateEndDate($optimizationIntegrations);
        }
    }


    private function getOptimizationRulesToReCalculatedScores() {
        $optimizationIntegrations = $this->optimizationIntegrationManager->all();
        $optimizationRules = [];

        foreach ($optimizationIntegrations as $optimizationIntegration) {
            if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                continue;
            }
            if (!$this->isOutOfDate($optimizationIntegration)) {
                continue;
            }

            $optimizationRule = $optimizationIntegration->getOptimizationRule();
            if ($optimizationRule instanceof OptimizationRuleInterface && !in_array($optimizationRule, $optimizationRules)) {
                array_push($optimizationRules, $optimizationRule);
            }
        }

        return $optimizationRules;

    }

    /**
     * @param $optimizationIntegrations
     */
    private function updateStartDate($optimizationIntegrations)
    {
        foreach ($optimizationIntegrations as $optimizationIntegration) {
            if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                continue;
            }
            $startRescoreAt = new DateTime('now');
            $optimizationIntegration->setStartRescoreAt($startRescoreAt);
            $this->optimizationIntegrationManager->save($optimizationIntegration);
        }
    }

    /**
     * @param $optimizationIntegrations
     */
    private function updateEndDate($optimizationIntegrations)
    {
        foreach ($optimizationIntegrations as $optimizationIntegration) {
            if (!$optimizationIntegration instanceof OptimizationIntegrationInterface) {
                continue;
            }
            $endRescoreAt = new DateTime('now');
            $optimizationIntegration->setEndRescoreAt($endRescoreAt);
            $this->optimizationIntegrationManager->save($optimizationIntegration);
        }
    }
}