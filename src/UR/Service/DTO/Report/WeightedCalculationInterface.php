<?php


namespace UR\Service\DTO\Report;


interface WeightedCalculationInterface
{
    /**
     * @param $field
     * @return boolean
     */
    public function hasCalculatingField($field);

    /**
     * @param $calculatingField
     * @return string|null
     */
    public function getFrequencyField($calculatingField);

    /**
     * @param $calculatingField
     * @return string|null
     */
    public function getWeightedField($calculatingField);

    /**
     * @return bool
     */
    public function hasCalculation();
}