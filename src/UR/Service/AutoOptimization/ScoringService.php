<?php


namespace UR\Service\AutoOptimization;

use UR\DomainManager\LearnerManagerInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Model\Core\LearnerInterface;

class ScoringService implements ScoringServiceInterface
{
    const CURRENT_MODEL = 'LINEAR_REGRESSION_MODEL';
    const CURRENT_CONVERTER = 'LINEAR_REGRESSION_CONVERTER';
    /**
     * @var array
     */
    private $learnerModels;

    /** @var  array */
    private $trainingDataConverters;

    /** @var LearnerManagerInterface  */
    private $learnerManager;

    /**
     * ScoringService constructor.
     * @param LearnerManagerInterface $learnerManager
     */
    function __construct(LearnerManagerInterface $learnerManager)
    {
        $this->learnerManager = $learnerManager;
    }

    /**
     * @inheritdoc
     */
    public function makeOnePrediction(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifiers, $condition)
    {
        // Step 1: Get model from data base
        $learnerModals = [];
        foreach ($identifiers as $identifier) {
            $learnerModals[] = $this->getLearnerModelFromDataBase($autoOptimizationConfig, $identifier);
        }
        // Step 2: Make a prediction
    }

    /**
     * @inheritdoc
     */
    public function makeMultiplePredictions(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifiers, $conditions)
    {
        // Step 1: Get model from data base
        $learnerModals = [];
        foreach ($identifiers as $identifier) {
            $learnerModals[] = $this->getLearnerModelFromDataBase($autoOptimizationConfig, $identifier);
        }
        // Step 2: Convert conditions to multiple sub condition. Each condition is the input for predicting of model
        $conditions = $this->createMultipleConditions($conditions);
        
        // Step 3: make prediction for multiple sub condition
    }


    /**
     * @return array
     */
    public function getLearnerModels()
    {
        return $this->learnerModels;
    }

    /**
     * @param array $learnerModels
     */
    public function setLearnerModels($learnerModels)
    {
        if (!is_array($learnerModels)) {
            $learnerModels = [$learnerModels];
        }

        foreach ($learnerModels as $learnerModel) {
            if (!$learnerModel instanceof LearnerInterface) {
                continue;
            }
            $this->learnerModels[] = $learnerModel;
        }
    }

    /**
     * @return mixed|LearnerInterface
     */
    public function getTrainingDataConverters()
    {
        return $this->trainingDataConverters;
    }

    /**
     * @param mixed|LearnerInterface $trainingDataConverters
     */
    public function setTrainingDataConverters($trainingDataConverters)
    {
        if (!is_array($trainingDataConverters)) {
            $trainingDataConverters = [$trainingDataConverters];
        }

        foreach ($trainingDataConverters as $trainingDataConverter) {
            if (!$trainingDataConverter instanceof LearnerInterface) {
                continue;
            }
            $this->$trainingDataConverters[] = $trainingDataConverter;
        }
    }

    /**
     * @inheritdoc
     */
    private function getLearnerModelFromDataBase(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier)
    {
        return $this->learnerManager->getLearnerModel($autoOptimizationConfig, $identifier);
    }

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @return array
     */
    private function forecastFactorValues(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier)
    {
        $forecastValues = [];

        return $forecastValues;
    }

    /**
     * return an array which an element is a condition for predicting
     * @param array $conditions
     */
    private function createMultipleConditions(array $conditions)
    {

    }
}