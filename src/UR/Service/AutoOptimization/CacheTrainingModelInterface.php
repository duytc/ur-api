<?php

namespace UR\Service\AutoOptimization;


use phpDocumentor\Reflection\Types\Object_;
use UR\Model\Core\AutoOptimizationConfigInterface;

interface CacheTrainingModelInterface
{
    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @param Object_ $model
     */
    public function saveTrainingModel(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier, Object_ $model);

    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @return mixed
     */
    public function getTrainingModel(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier);
}