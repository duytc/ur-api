<?php

namespace UR\Service\AutoOptimization;

use UR\Model\Core\AutoOptimizationConfigInterface;

interface ScoringServiceInterface
{
    const AUTO_OPTIMIZATION_CONFIG_ID_KEY = 'autoOptimizationConfigId';
    const TOKEN_KEY = 'token';
    const IDENTIFIERS_KEY = 'identifiers';
    const CONDITIONS_KEY = 'conditions';

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