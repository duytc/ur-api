<?php


namespace UR\Service\OptimizationRule;



use PHPUnit\Runner\Exception;
use Psr\Log\LoggerInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\RestClientTrait;

class OptimizationLearningFacadeService implements OptimizationLearningFacadeServiceInterface
{
    use RestClientTrait;

    /**
     * @var DataTrainingCollectorInterface
     */
    private $dataTrainingCollector;
    /**
     * @var DataTrainingTableService
     */
    private $dataTrainingTableService;
    /**
     * @var OptimizationRuleScoreServiceInterface
     */
    private $optimizationRuleScoreService;
    private $activateLearnerLink;
    private $calculateScoreLink;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(DataTrainingCollectorInterface $dataTrainingCollector,
                                DataTrainingTableService $dataTrainingTableService,
                                OptimizationRuleScoreServiceInterface $optimizationRuleScoreService,
                                $activateLearnerLink,
                                $calculateScoreLink,
                                LoggerInterface $logger)
    {
        $this->dataTrainingCollector = $dataTrainingCollector;
        $this->dataTrainingTableService = $dataTrainingTableService;
        $this->optimizationRuleScoreService = $optimizationRuleScoreService;
        $this->activateLearnerLink = $activateLearnerLink;
        $this->calculateScoreLink = $calculateScoreLink;
        $this->logger = $logger;
    }

    /**
     * @param OptimizationRuleInterface $reactiveLearningOptimizationRule
     * @throws \Exception
     */
    public function calculateNewScores(OptimizationRuleInterface $reactiveLearningOptimizationRule)
    {
        if (!$reactiveLearningOptimizationRule instanceof OptimizationRuleInterface) {
            return;
        }

        $data = $this->dataTrainingCollector->buildDataForOptimizationRule($reactiveLearningOptimizationRule);

        //Table: data_training_58
        $this->importTrainingData($reactiveLearningOptimizationRule, $data);

        //Table: core_learner
        $activateLearnerResult = $this->activateLearner($reactiveLearningOptimizationRule);
        if (!$activateLearnerResult) {
            $this->logger->error(sprintf('Activate learning process failed for optimization rule %d', $reactiveLearningOptimizationRule->getId()));
            return;
        }

        //Table: __optimization_rule_score_58
        $this->optimizationRuleScoreService->createOptimizationRuleScoreTable($reactiveLearningOptimizationRule);

        //Table: __optimization_rule_score_58
        $this->activeCalculating($reactiveLearningOptimizationRule);
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @param $data
     * @throws \Exception
     */
    private function importTrainingData(OptimizationRuleInterface $optimizationRule, $data)
    {
        try {
            $this->dataTrainingTableService->importDataToDataTrainingTable($data, $optimizationRule);
        } catch (Exception $e) {
            return;
        }
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return bool
     * @throws \Exception
     */
    private function activateLearner(OptimizationRuleInterface $optimizationRule)
    {
        $method = 'POST';
        $data = [
            'optimizationRuleId' => $optimizationRule->getId(),
            'token' => $optimizationRule->getToken()];

        try {
            $result = $this->callRestAPI($method, $this->activateLearnerLink, json_encode($data));

            $result = json_decode($result, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning('There is a error when activating learning process: could not parse response');
                return false;
            }

            if (array_key_exists('status', $result) && $result['status'] != 200) {
                $message = array_key_exists('message', $result) ? $result['message'] : '';
                $this->logger->warning(sprintf('There is a error when activating learning process: got status %s, message: %s', $result['status'], $message));
                return false;
            }
        } catch (Exception $e) {
            $this->logger->warning('There is a error when activating learning process');
            return false;
        }

        return true;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @throws \Exception
     */
    private function activeCalculating(OptimizationRuleInterface $optimizationRule)
    {
        $method = 'POST';
        $data = [
            'optimizationRuleId' => $optimizationRule->getId(),
            'token' => $optimizationRule->getToken()];

        try {
            $this->logger->info('Activate calculating process');
            $this->callRestAPI($method, $this->calculateScoreLink, json_encode($data));
        } catch (Exception $e) {
            $this->logger->warning('There is a error when calculating scores');
        }
    }
}