<?php


namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;

interface AutoOptimizationConfigRepositoryInterface extends ObjectRepository
{
    /**
     * @param int $autoOptimizationConfigId
     */
    public function deleteDataTrainingTableWhenDeleteAutoOptimizationConfig($autoOptimizationConfigId);

}