<?php


namespace UR\Repository\Core;


use Doctrine\Common\Persistence\ObjectRepository;

interface AutoOptimizationConfigRepositoryInterface extends ObjectRepository
{
    /**
     * remove a "add conditional transform value" id in "add conditional value transform"
     * @param $id
     * @return mixed
     */
    public function removeAddConditionalTransformValue($id);
}