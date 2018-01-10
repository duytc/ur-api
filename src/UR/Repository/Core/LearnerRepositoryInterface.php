<?php


namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\AutoOptimizationConfigInterface;

interface LearnerRepositoryInterface extends ObjectRepository
{
    /**
     * @param AutoOptimizationConfigInterface $autoOptimizationConfig
     * @param $identifier
     * @return mixed
     */
    public function getLearnerByAutoOptimizationAndIdentifier(AutoOptimizationConfigInterface $autoOptimizationConfig, $identifier);
}