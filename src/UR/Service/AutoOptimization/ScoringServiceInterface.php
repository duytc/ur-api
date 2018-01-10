<?php

namespace UR\Service\AutoOptimization;

use UR\Model\Core\AutoOptimizationConfigInterface;

interface ScoringServiceInterface
{
    const REGRESSION_LINEAR_MODEL = 'LinearRegression';
    const COEFFICIENT_KEY = 'coefficient';
    const INTERCEPT_KEY = 'intercept';
    const ALL_FACTORS_KEY = 'all';
    const OBJECTIVE_DEFAULT_VALUE = 0;

    /**
     * Score for multiple identifiers and conditions
     *
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifiers
     * @param $conditions
     * @return mixed
     */
    public function predict(AutoOptimizationConfigInterface $autoOptimizationConfig, array $identifiers, array $conditions);
}