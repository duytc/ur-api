<?php

namespace UR\Service\AutoOptimization;

use UR\Model\Core\AutoOptimizationConfigInterface;

interface ScoringServiceInterface
{
    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifiers
     * @param $condition
     * @return mixed
     */
    public function makeOnePrediction(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifiers, $condition);

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifiers
     * @param $conditions
     * @return mixed
     */
    public function makeMultiplePredictions(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifiers, $conditions);
}