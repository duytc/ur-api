<?php


namespace UR\Util;


use UR\Exception\InvalidArgumentException;

trait CalculateWeightedValueTrait
{
    /**
     * @param array $reports
     * @param string $frequencyField
     * @param string $weightField
     * @return float|null
     */
    protected function calculateWeightedValue(array $reports, $frequencyField = 'estCpm', $weightField = 'EstRevenue')
    {
        if (null === $reports) {
            throw new InvalidArgumentException('Expect a valid report');
        }

        if (empty($reports)) {
            return null;
        }

        $total = 0;
        $totalWeight = 0;
        $count = 0;
        $totalFrequency = 0;
        foreach ($reports as $report) {
            try {
                $number = $report[$frequencyField];
                $weight = $report[$weightField];
                $total += $number * $weight;
                $totalWeight += $weight;
                $totalFrequency += $number;
                $count++;
            } catch (\Exception $e) {
            }
        }

        if ($totalWeight <= 0) {
            return $this->getRatio($totalFrequency, $count);
        }

        return $this->getRatio($total, $totalWeight);
    }

    /**
     * @param $numerator
     * @param $denominator
     * @return float|null
     */
    abstract protected function getRatio($numerator, $denominator);
}