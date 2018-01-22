<?php


namespace UR\Service\AutoOptimization\LearnerModel\LinearRegressionLearner;

use UR\DomainManager\LearnerManagerInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\LearnerInterface;
use UR\Service\AutoOptimization\LearnerModel\LearnerModelInterface;

class LinearRegression implements LearnerModelInterface
{

    /** @var LearnerManagerInterface */
    private $learnerManager;
    /** @var LearnerInterface */
    private $learner;
    /** @var  $isBestModel */

    /**
     * LinearRegression constructor.
     * @param LearnerManagerInterface $learnerManager
     */
    function __construct(LearnerManagerInterface $learnerManager)
    {
        $this->learnerManager = $learnerManager;
    }

    /**
     * @inheritdoc
     */
    public function getBestFitLearnerModel(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier)
    {
        $learnerInDataBase = $this->getBestLearnerModelFromDataBase($autoOptimizationConfig, $identifier);

        if (empty($learnerInDataBase)) {
            return [];
        }

        $newInstance = new static($this->learnerManager);
        $newInstance->setLearner($learnerInDataBase);

        return $newInstance;
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @return mixed
     */
    private function getBestLearnerModelFromDataBase(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier)
    {
        $type = LearnerModelInterface::REGRESSION_LINEAR_MODEL;
        $learnerModel = $this->learnerManager->getLearnerByParams($autoOptimizationConfig, $identifier, $type);

        if (!$learnerModel instanceof LearnerInterface) {
            return [];
        }

        /* The algorithm to decide if this model is the best for this identifier is temporary base on the type
           because now we have only one learner model. This algorithm will be changed in the future when we there
           are many learner models.
        */
        /** @var LearnerInterface $learnerModel */
        if (!$learnerModel->getType() == $type) {
            return [];
        }

        return $learnerModel;
    }

    /**
     * @param LearnerInterface $learner
     */
    private function setLearner(LearnerInterface $learner)
    {
        $this->learner = $learner;
    }

    /**
     * @inheritdoc
     */
    public function predict(array $condition)
    {
        return $this->makeOnePredictionWithOneCondition($this->learner, $condition);
    }

    /**
     * @param LearnerInterface $learner
     * @param $condition
     * @return float|int|mixed
     * @throws \Exception
     */
    private function makeOnePredictionWithOneCondition(LearnerInterface $learner, $condition)
    {
        $learnerModel = $learner->getModel();
        if (!is_array($learnerModel) || !array_key_exists('' . LearnerModelInterface::COEFFICIENT_KEY . '', $learnerModel) || !array_key_exists('' . LearnerModelInterface::INTERCEPT_KEY . '', $learnerModel)) {
            return LearnerModelInterface::OBJECTIVE_DEFAULT_VALUE;
        }

        $oneInputOfLearnerModel = $this->createInputForLearnerModel($learner, $condition);
        if (empty($oneInputOfLearnerModel)) {
            return [];
        }

        return $this->calculateOneObjective($learnerModel, $oneInputOfLearnerModel);
    }

    /**
     * @param LearnerInterface $learner
     * @param $condition
     * @return array
     */
    private function createInputForLearnerModel(LearnerInterface $learner, $condition)
    {
        $inputForLearnerModel = [];

        $forecastFactorValues = $learner->getForecastFactorValues();
        $categoricalFieldWeights = $learner->getCategoricalFieldWeights();
        if (empty($forecastFactorValues || empty($categoricalFieldWeights))) {
            return [];
        }

        if (!$learner->getAutoOptimizationConfig() instanceof AutoOptimizationConfigInterface) {
            return [];
        }
        $factors = $learner->getAutoOptimizationConfig()->getFactors();

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
            if (array_key_exists($factor, $categoricalFieldWeights) && is_string($value)) {
                $allWeight = $categoricalFieldWeights[$factor];
                $inputForLearnerModel[$factor] = $this->getWeightOfOneField($allWeight, $value);
            }
        }

        return $inputForLearnerModel;
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
        $coefficients = $learnerModel[LearnerModelInterface::COEFFICIENT_KEY];
        $intercept = $learnerModel[LearnerModelInterface::INTERCEPT_KEY];

        $total = LearnerModelInterface::OBJECTIVE_DEFAULT_VALUE;
        foreach ($coefficients as $factor => $coefficient) {
            if (!array_key_exists($factor, $input)) {
                throw new \Exception(sprintf('Input vector is not valid, key = %s does not exist', $factor));
            }

            if(!is_numeric($coefficient)) {
                $coefficient =  0;
            }

            $total += $coefficient * $input[$factor];
        }

        if (!is_numeric($intercept)) {
            $intercept =  0;
        }

        $objective = $total + $intercept;

        return $objective;
    }
}