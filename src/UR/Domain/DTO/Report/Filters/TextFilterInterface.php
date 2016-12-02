<?php


namespace UR\Domain\DTO\Report\Filters;


interface TextFilterInterface extends AbstractFilterInterface
{
    /**
     * @return string
     */
    public function getComparisonType();

    /**
     * @return string|array due to comparisonType.
     */
    public function getComparisonValue();
}