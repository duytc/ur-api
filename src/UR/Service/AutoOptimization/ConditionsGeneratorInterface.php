<?php

namespace UR\Service\AutoOptimization;

use UR\Model\Core\AutoOptimizationConfigInterface;

interface ConditionsGeneratorInterface
{
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