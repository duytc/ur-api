<?php


namespace UR\Domain\DTO\Report\Transforms;


interface ComparisonPercentTransformInterface extends TransformInterface
{
    /**
     * @return mixed
     */
    public function getNumerator();

    /**
     * @return mixed
     */
    public function getDenominator();

    /**
     * @return mixed
     */
    public function getField();
}