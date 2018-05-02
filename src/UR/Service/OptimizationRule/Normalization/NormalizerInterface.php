<?php

namespace UR\Service\OptimizationRule\Normalization;

interface NormalizerInterface
{
    const NUMBER_NORMALIZER_SUPPORT_VALUE = 0.2;
    const NUMBER_NORMALIZER_COUNT_SAMPLE_VALUE = 30;
    /**
     * @param $rows
     * @param $segment
     * @return boolean
     */
    public  function isSupport($rows, $segment);

    /**
     * @param $text
     * @return mixed
     */
    public function normalizeText($text);
}