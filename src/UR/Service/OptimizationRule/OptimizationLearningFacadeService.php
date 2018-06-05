<?php


namespace UR\Service\OptimizationRule;


use PHPUnit\Runner\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
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
     * @return bool|mixed
     * @throws \Exception
     */
    public function calculateNewScores(OptimizationRuleInterface $reactiveLearningOptimizationRule)
    {
        if (!$reactiveLearningOptimizationRule instanceof OptimizationRuleInterface) {
            return false;
        }

        $data = $this->dataTrainingCollector->buildDataForOptimizationRule($reactiveLearningOptimizationRule);

        //Table: data_training_58
        $this->importTrainingData($reactiveLearningOptimizationRule, $data);

        //Table: core_learner
        $returnCode = $this->activateLearner($reactiveLearningOptimizationRule);

        if ($returnCode == Response::HTTP_OK) {
            //Table: __optimization_rule_score_58
            $this->optimizationRuleScoreService->createOptimizationRuleScoreTable($reactiveLearningOptimizationRule);
            //Table: __optimization_rule_score_58
            return $this->activeCalculatingScores($reactiveLearningOptimizationRule);
        }

        if ($returnCode == Response::HTTP_CREATED) {
            $this->logger->warning(sprintf('Data training of optimization rule %d does not change, no need to recalculate scores', $reactiveLearningOptimizationRule->getId()));
            return true;
        }

        $this->logger->error(sprintf('Activate learning process failed for optimization rule %d', $reactiveLearningOptimizationRule->getId()));
        return false;
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

        $returnCode =  Response::HTTP_BAD_REQUEST;

        try {
            $result = $this->callRestAPI($method, $this->activateLearnerLink, json_encode($data));

            $result = json_decode($result, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning('There is a error when activating learning process: could not parse response');

                return $returnCode;
            }

            if (array_key_exists('status', $result)) {
                $returnCode =  $result['status'];
            }

        } catch (Exception $e) {
            $this->logger->warning(sprintf('There is an error when activating learning process. Detail: %s', $e->getMessage()));

            return $returnCode;
        }

        return $returnCode;
    }

    /**
     * @param OptimizationRuleInterface $optimizationRule
     * @return bool
     * @throws \Exception
     */
    private function activeCalculatingScores(OptimizationRuleInterface $optimizationRule)
    {
        $method = 'POST';
        $data = [
            'optimizationRuleId' => $optimizationRule->getId(),
            'token' => $optimizationRule->getToken()];

        try {
            $this->logger->info('Activate calculating process');
            $result = $this->callRestAPI($method, $this->calculateScoreLink, json_encode($data));

            $result = json_decode($result, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->warning('There is a error when activating calculating process: could not parse response');
                return false;
            }

            if (array_key_exists('status', $result) && $result['status'] != 200) {
                $message = array_key_exists('message', $result) ? $result['message'] : '';
                $this->logger->warning(sprintf('There is a error when activating calculating process: got status %s, message: %s', $result['status'], $message));
                return false;
            }

        } catch (Exception $e) {
            $this->logger->warning(sprintf('There is a error when calculating scores. Detail: %s', $e->getMessage()));
        }

        return true;
    }
}