<?php


namespace UR\Domain\DTO\Report\Filters;


interface NumberFilterInterface extends FilterInterface
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