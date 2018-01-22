<?php

namespace UR\Service\AutoOptimization;

use UR\Model\Core\AutoOptimizationConfigInterface;

interface ConditionsGeneratorInterface
{
    const FACTOR_KEY = 'factor';
    const VALUES_KEY = 'values';
    const IS_ALL_KEY = 'isAll';

    /**
     * Convert conditions to multiple input that use for learner model
     *
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param array $conditions
     * @return array
     * @throws \Exception
     */
    public function generateMultipleConditions(AutoOptimizationConfigInterface $autoOptimizationConfig, array $conditions);
}