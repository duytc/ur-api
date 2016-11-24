<?php


namespace UR\Util;


trait CalculateRatiosTrait
{
    /**
     * @param $numerator
     * @param $denominator
     * @return float|null
     */
    protected function getRatio($numerator, $denominator)
    {
        $ratio = null;

        if (is_numeric($denominator) && $denominator > 0 && is_numeric($numerator)) {
            $ratio = abs($numerator- $denominator) / $denominator;
        }

        return $ratio;
    }

    /**
     * @param $numerator
     * @param $denominator
     * @return float
     */
    protected function getPercentage($numerator, $denominator)
    {
        $ratio = $this->getRatio($numerator, $denominator);

        if (null == $ratio) {
            return 0.00;
        }

        if ($ratio > 1.00) {
            $ratio = 1.00;
        }

        $ratio = round($ratio, 4);

        return $ratio;
    }
}