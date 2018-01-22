<?php


namespace UR\Service\AutoOptimization\LearnerModel;


use UR\Model\Core\AutoOptimizationConfigInterface;

interface LearnerModelInterface
{
    const REGRESSION_LINEAR_MODEL = 'LinearRegression';
    const COEFFICIENT_KEY = 'coefficient';
    const INTERCEPT_KEY = 'intercept';
    const ALL_FACTORS_KEY = 'all';
    const OBJECTIVE_DEFAULT_VALUE = 0;
    const MAX_DECIMAL = 5;

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @return mixed
     */
    public function getBestFitLearnerModel(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier);

    /**
     * @param array $conditions
     * @return mixed
     */
    public function predict(array $conditions);
}