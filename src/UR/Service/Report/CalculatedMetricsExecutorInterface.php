<?php

namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\PublicSimpleException;

interface CalculatedMetricsExecutorInterface
{
    const TODAY = '$today';
    const YESTERDAY = '$yesterday';
    const START_DATE = '$startDate';
    const END_DATE = '$endDate';

    const EXPRESSION_MACROS = 'macros';
    const EXPRESSION_VARIABLES = 'variables';
    const EXPRESSION_METRICS = 'metrics';

    const TYPE_NUMBER = 'number';
    const TYPE_DECIMAL = 'decimal';

    /**
     * @param $calculatedMetricsObject
     * @param $showInTotals
     * @param $total
     * @param ParamsInterface $params
     * @return array
     * @throws PublicSimpleException
     */
    public function doCalculatedMetrics($calculatedMetricsObject, $showInTotals, $total, ParamsInterface $params);

    /**
     * @param $macroName
     * @param $params
     * @return mixed
     */
    public function getMacrosTimeValue($macroName, ParamsInterface $params);

    /**
     * @param $macroName
     * @param $params
     * @return mixed
     */
    public function getSubMacrosValue($macroName, ParamsInterface $params);
}