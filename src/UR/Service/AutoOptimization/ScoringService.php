<?php


namespace UR\Service\AutoOptimization;

use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\AutoOptimization\LearnerModel\LearnerModelInterface;

class ScoringService implements ScoringServiceInterface
{

    /** @var LearnerModelInterface[] $learnerModels */
    private $learnerModels = [];
    /**
     * @var ConditionsGenerator
     */
    private $conditionsGenerator;

    /**
     * ScoringService constructor.
     * @param ConditionsGenerator $conditionsGenerator
     * @param array $learnerModels
     */
    function __construct(ConditionsGenerator $conditionsGenerator, array $learnerModels)
    {
        foreach ($learnerModels as $learnerModel) {
            if ($learnerModel instanceof LearnerModelInterface) {
                $this->learnerModels[] = $learnerModel;
            }
        }
        $this->conditionsGenerator = $conditionsGenerator;
    }

    /**
     * @inheritdoc
     */
    public function predict(AutoOptimizationConfigInterface $autoOptimizationConfig, array $identifiers, array $conditions)
    {
        $conditions = $this->conditionsGenerator->generateMultipleConditions($autoOptimizationConfig, $conditions);
        $expectedObjective = $autoOptimizationConfig->getExpectedObjective();

        $fitModels = $this->getLearnerModels($autoOptimizationConfig, $identifiers);

        if (!is_array($fitModels)) {
            return [];
        }

        $normalizePredictions = $this->makeMultiplePredictionsWithManyConditions($fitModels, $conditions);

        foreach ($normalizePredictions as $key => $normalizePrediction) {
            $normalizePredictions[$key] = $this->sortByExpectedObjective($expectedObjective, $normalizePrediction);
        }

        return $normalizePredictions;
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param array $identifiers
     * @return array
     */
    private function getLearnerModels(AutoOptimizationConfigInterface $autoOptimizationConfig, array $identifiers)
    {
        $fitModels = [];
        foreach ($identifiers as $identifier) {
            foreach ($this->learnerModels as $learnerModel) {
                $bestModel = $learnerModel->getBestFitLearnerModel($autoOptimizationConfig, $identifier);

                $fitModels[$identifier] = $bestModel instanceof LearnerModelInterface ? $bestModel : [];
                if (!empty($fitModels[$identifier])) {
                    break;
                }
            }
        }

        return $fitModels;
    }

    /**
     * @param array $learnerModels
     * @param array $conditions
     * @return array
     */
    private function makeMultiplePredictionsWithManyConditions(array $learnerModels, array $conditions)
    {
        $predictions = [];
        foreach ($conditions as $condition) {
            $keyOfPrediction = $this->getKeyOfPrediction($condition);
            $predictions[$keyOfPrediction] = $this->makeMultiplePredictionsWithOneCondition($learnerModels, $condition);
        }

        return $predictions;
    }

    /**
     * @param $condition
     * @return string
     */
    private function getKeyOfPrediction($condition)
    {
        if (!is_array($condition) || empty($condition)) {
            return LearnerModelInterface::ALL_FACTORS_KEY;
        }

        return implode(",", $condition);
    }

    /**
     * @param array $learnerModels
     * @param $condition
     * @return array
     */
    private function makeMultiplePredictionsWithOneCondition(array $learnerModels, $condition)
    {
        $predictions = [];
        foreach ($learnerModels as $identifier => $learnerModel) {
            if (!$learnerModel instanceof LearnerModelInterface) {
                $predictions[$identifier] = LearnerModelInterface::OBJECTIVE_DEFAULT_VALUE;
                continue;
            }

            $predictions[$identifier] = $learnerModel->predict($condition);
        }
        $normalizePredictions = $this->normalizePredictions($predictions);

        return $normalizePredictions;
    }

    /**
     * Convert objective to [0,1]
     * @param array $predictions
     * @return array
     */
    private function normalizePredictions(array $predictions)
    {
        $normalizePredictions = [];

        $totalPredictions = array_sum($predictions);
        $totalPredictions = $totalPredictions == 0 ? 1 : $totalPredictions;

        foreach ($predictions as $identifier => $prediction) {
            $rawPrediction = $prediction / $totalPredictions;
            $normalizePredictions[$identifier] = number_format($rawPrediction, LearnerModelInterface::MAX_DECIMAL);
        }

        return $normalizePredictions;
    }

    /**
     * @param $expectedObjective
     * @param $normalizePredictions
     * @return mixed
     */
    private function sortByExpectedObjective($expectedObjective, $normalizePredictions)
    {
        if (AutoOptimizationConfigInterface::MIN_OBJECTIVE === $expectedObjective) {
            asort($normalizePredictions);
        } else {
            arsort($normalizePredictions);
        }

        return $normalizePredictions;
    }
}