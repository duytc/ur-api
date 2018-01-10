<?php


namespace UR\Service\AutoOptimization;

use UR\DomainManager\LearnerManagerInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;

class ScoringService implements ScoringServiceInterface
{
    const MAX_DECIMAL = 5;

    /** @var LearnerManagerInterface */
    private $learnerManager;

    /** @var ConditionsGeneratorInterface */
    private $conditionsGenerator;

    /**
     * ScoringService constructor.
     * @param LearnerManagerInterface $learnerManager
     * @param ConditionsGeneratorInterface $conditionsGenerator
     */
    function __construct(LearnerManagerInterface $learnerManager, ConditionsGeneratorInterface $conditionsGenerator)
    {
        $this->learnerManager = $learnerManager;
        $this->conditionsGenerator = $conditionsGenerator;
    }

    /**
     * @inheritdoc
     */
    public function predict(AutoOptimizationConfigInterface $autoOptimizationConfig, array $identifiers, array $conditions)
    {
        $multipleConditions = $this->conditionsGenerator->generateMultipleConditions($autoOptimizationConfig, $conditions);
        $predictions = $this->makeMultiplePredictionsWithManyConditions($autoOptimizationConfig, $identifiers, $multipleConditions);

        return $predictions;
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param array $identifiers
     * @param array $conditions
     * @return array
     * @throws \Exception
     */
    private function makeMultiplePredictionsWithManyConditions(AutoOptimizationConfigInterface $autoOptimizationConfig, array $identifiers, array $conditions)
    {
        $predictions = [];
        foreach ($conditions as $condition) {
            $keyOfPrediction = $this->getKeyOfPrediction($condition);
            $predictions[$keyOfPrediction] = $this->makeMultiplePredictionsWithOneCondition($autoOptimizationConfig, $identifiers, $condition);
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
            return ScoringServiceInterface::ALL_FACTORS_KEY;
        }

        return implode(",", $condition);
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param array $identifiers
     * @param $condition
     * @return array
     * @throws \Exception
     */
    private function makeMultiplePredictionsWithOneCondition(AutoOptimizationConfigInterface $autoOptimizationConfig, array $identifiers, $condition)
    {
        $predictions = [];

        foreach ($identifiers as $identifier) {
            $predictions[$identifier] = $this->makeOnePredictionWithOneCondition($autoOptimizationConfig, $identifier, $condition);
        }

        $normalizePredictions = $this->normalizePredictions($predictions);

        return $normalizePredictions;
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @param $condition
     * @return float|int|mixed
     * @throws \Exception
     */
    private function makeOnePredictionWithOneCondition(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier, $condition)
    {
        $learnerModel = $this->getLearnerModelFromDataBase($autoOptimizationConfig, $identifier);
        if (!is_array($learnerModel) || !array_key_exists('' . ScoringServiceInterface::COEFFICIENT_KEY . '', $learnerModel) || !array_key_exists('' . ScoringServiceInterface::INTERCEPT_KEY . '', $learnerModel)) {
            return ScoringServiceInterface::OBJECTIVE_DEFAULT_VALUE;
        }
        $oneInputOfLearnerModel = $this->createInputForLearnerModel($autoOptimizationConfig, $identifier, $condition);

        return $this->calculateOneObjective($learnerModel, $oneInputOfLearnerModel);
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @return mixed
     */
    private function getLearnerModelFromDataBase(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier)
    {
        $type = $this->getBestFitLearnerModel($autoOptimizationConfig, $identifier);
        return $this->learnerManager->getLearnerModelByParams($autoOptimizationConfig, $identifier, $type);
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @return string
     */
    private function getBestFitLearnerModel(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier)
    {
        return ScoringServiceInterface::REGRESSION_LINEAR_MODEL;
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @param $condition
     * @return array
     */
    private function createInputForLearnerModel(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier, $condition)
    {
        $inputForLearnerModel = [];

        $forecastFactorValues = $this->getForecastFactorValues($autoOptimizationConfig, $identifier);
        $categoricalFieldWeights = $this->getCategoricalFieldWeights($autoOptimizationConfig, $identifier);
        $factors = $autoOptimizationConfig->getFactors();

        foreach ($factors as $factor) {
            if (array_key_exists($factor, $condition)) {
                $inputForLearnerModel[$factor] = $condition[$factor];
            } else if (array_key_exists($factor, $forecastFactorValues)) {
                $inputForLearnerModel[$factor] = $forecastFactorValues[$factor];
            } else {

            }
        }

        // Get categorical field value
        foreach ($inputForLearnerModel as $factor => $value) {
            if (array_key_exists($factor, $categoricalFieldWeights)) {
                $allWeight = $categoricalFieldWeights[$factor];
                $inputForLearnerModel[$factor] = $this->getWeightOfOneField($allWeight, $value);
            }
        }

        return $inputForLearnerModel;
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @return array
     */
    private function getForecastFactorValues(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier)
    {
        $type = $this->getBestFitLearnerModel($autoOptimizationConfig, $identifier);
        return $this->learnerManager->getForecastFactorsValuesByByParams($autoOptimizationConfig, $identifier, $type);
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @return mixed
     */
    private function getCategoricalFieldWeights(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier)
    {
        $type = $this->getBestFitLearnerModel($autoOptimizationConfig, $identifier);

        return $this->learnerManager->getCategoricalFieldWeightsByParams($autoOptimizationConfig, $identifier, $type);
    }

    /**
     * @param array $weights
     * @param $stringValues
     * @return mixed
     */
    private function getWeightOfOneField(array $weights, $stringValues)
    {
        if (array_key_exists($stringValues, $weights)) {
            return $weights[$stringValues];
        }

        $maxValues = max($weights);
        if (is_array($maxValues)) {
            return array_shift($maxValues);
        }

        return $maxValues;
    }

    /**
     * @param array $learnerModel
     * @param array $input
     * @return float|int|mixed
     * @throws \Exception
     */
    private function calculateOneObjective(array $learnerModel, array $input)
    {
        $coefficients = $learnerModel[ScoringServiceInterface::COEFFICIENT_KEY];
        $intercept = $learnerModel[ScoringServiceInterface::INTERCEPT_KEY];

        $coefficients = $this->arrayFlatten($coefficients);

        $total = ScoringServiceInterface::OBJECTIVE_DEFAULT_VALUE;
        foreach ($coefficients as $factor => $coefficient) {
            if (!array_key_exists($factor, $input)) {
                throw new \Exception(sprintf('Input vector is not valid, key = %s does not exist', $factor));
            }
            $total += $coefficient * $input[$factor];
        }
        $objective = $total + $intercept;

        return $objective;
    }

    /**
     * @param array $array
     * @return array
     */
    private function arrayFlatten(array $array)
    {
        $flatten = array();
        array_walk_recursive($array, function ($value, $key) use (&$flatten) {
            $flatten[$key] = $value;
        });

        return $flatten;
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
            $normalizePredictions[$identifier] = number_format($rawPrediction, self::MAX_DECIMAL);
        }

        return $normalizePredictions;
    }
}