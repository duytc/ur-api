<?php


namespace UR\Domain\DTO\Report\Filters;


interface TextFilterInterface extends AbstractFilterInterface
{
    /**
     * @return mixed
     */
    public function getComparisonType();

    /**
     * @return mixed
     */
    public function getComparisonValue();
}