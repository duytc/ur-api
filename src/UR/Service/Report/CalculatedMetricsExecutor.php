<?php
namespace UR\Service\Report;

use DateTime;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Response;
use UR\Domain\DTO\Report\CalculatedMetrics\AddCalculatedMetrics;
use UR\Domain\DTO\Report\ParamsInterface;
use UR\Service\PublicSimpleException;

class CalculatedMetricsExecutor implements CalculatedMetricsExecutorInterface
{
    /**
     * @var SqlBuilderInterface
     */
    protected $sqlBuilder;

    /**
     * ReportGrouper constructor.
     * @param SqlBuilderInterface $sqlBuilder
     */
    public function __construct(SqlBuilderInterface $sqlBuilder)
    {
        $this->sqlBuilder = $sqlBuilder;
    }

    /**
     * @inheritdoc
     */
    public function doCalculatedMetrics($calculatedMetricsObject, $showInTotals, $total, ParamsInterface $params)
    {
        if (!is_array($calculatedMetricsObject) || empty($calculatedMetricsObject)) {
            return [];
        }
        $calculatedMetrics = [];
        try {
            $expressionLanguage = new ExpressionLanguage();
            foreach ($calculatedMetricsObject as $calculatedMetric) {
                if (!$calculatedMetric instanceof AddCalculatedMetrics) {
                    continue;
                }

                $fieldName = $calculatedMetric->getFieldName();
                $dataType = $calculatedMetric->getType();
                $expression = $calculatedMetric->getExpression();
                $defaultValue = $calculatedMetric->getDefaultValue();
                if (is_null($expression) && is_null($defaultValue)) {
                    throw new \Exception(sprintf('Need at least Expression or Default Value for calculated field can not be null'), Response::HTTP_BAD_REQUEST);
                }

                if (is_null($expression)) {
                    // set default value for this fieldName
                    $calculatedMetrics [$fieldName] = $defaultValue;
                    unset($fieldName, $expression, $defaultValue);

                    continue;
                }

                // step 0: get variable, metrics, metric macros
                $extractExpression = $this->extract_dynamic_contents($expression);

                // Step1: do with metric macros
                $expression = $this->doExpressionWithMetricMacro($expression, $params, $extractExpression['macros']);

                // Step2: do with variable
                $expression = $this->doExpressionWithMetricVariable($expression, $params, $extractExpression['variables']);

                // Step3: do with metrics
                $expression = $this->doExpressionWithMetrics($expression, $extractExpression['metrics'], $calculatedMetrics, $showInTotals, $total);
                // Step4 use eval to get result of expression

                // Step5: do expression
                $expressionResult = $expressionLanguage->evaluate($expression);
                $finalValue = isset($expressionResult) && !empty($expressionResult) ? $expressionResult : $defaultValue;

                $calculatedMetrics[$fieldName] = $this->getTrueValueByType($dataType, $finalValue);

                unset($fieldName, $expression, $finalValue, $valueForExpression, $defaultValue, $extractExpression, $expressionResult);
            }

            // step 6: filter calculated metrics with isVisible = true
            $calculatedMetrics = $this->filterCalculatedMetricsResultWithIsVisible($calculatedMetricsObject, $calculatedMetrics);

            unset($calculatedMetricsObject, $calculatedMetric, $expressionLanguage);
        } catch (\Exception $ex) {
            throw new PublicSimpleException(sprintf('Calculated Metrics error: %s.', $ex->getMessage()), $ex->getCode());
        }

        return $calculatedMetrics;
    }

    /**
     * @param $expression
     * @param $showInTotals
     * @param $total
     * @param $field
     * @return int|mixed
     * @throws PublicSimpleException
     */
    private function getValueMetricInTotal($expression, $showInTotals, $total, $field)
    {
        foreach ($showInTotals as $showInTotal) {
            if (!is_array($showInTotal)) {
                continue;
            }
            if (array_key_exists('aliasName', $showInTotal)) {
                foreach ($showInTotal['aliasName'] as $aliasNameItem) {
                    if ($aliasNameItem['aliasName'] == $field) {
                        $fieldInTotal = $aliasNameItem['originalName'];
                        break;
                    }
                }
            }
        }

        if (isset($fieldInTotal) && is_array($total) && array_key_exists($fieldInTotal, $total)) {
            $valueForExpression = $total[$fieldInTotal];
        }

        if (!isset($valueForExpression) && (strpos($expression, $field) == true)) {
            throw new PublicSimpleException(sprintf('Do not support macros: %s', $field), Response::HTTP_BAD_REQUEST);
        }

        return isset($valueForExpression) && !empty($valueForExpression) ? $valueForExpression : 0;
    }

    /**
     * @param $expression
     * @param ParamsInterface $params
     * @param $extractExpressionMacros
     * @return mixed
     * @throws \Exception
     */
    private function doExpressionWithMetricMacro($expression, ParamsInterface $params, $extractExpressionMacros)
    {
        if (!is_array($extractExpressionMacros) || empty($extractExpressionMacros)) {

            return $expression;
        }

        if (!preg_match_all('/\$(.*?)\)/', $expression, $matchesMetricMacroSummary)) {

            return $expression;
        };

        $fieldsInBracketMetricMacro = $matchesMetricMacroSummary[0];

        foreach ($fieldsInBracketMetricMacro as $index => $value) {

            // 1.0 get metric macros field name ex $revenue_6
            $metricFieldName = $this->checkMetricMacroNameInString($extractExpressionMacros, $value);

            if (empty($metricFieldName)) {
                continue;
            }

            //1.1 get sub metric macros such as $yesterday, $startDate, $endDate
            if (!preg_match_all('/\((.*)/m', $value, $matchesMetricMacroDetail, PREG_SET_ORDER, 0)) {

                return $expression;
            }

            $subMetricMacroExpression = str_replace([']', '[', ')'], '', $matchesMetricMacroDetail[0][1]);

            $arraySubMetricMacroExpression = explode(',', $subMetricMacroExpression);
            $newArraySubMetricMacroExpression = [];
            foreach ($arraySubMetricMacroExpression as $item) {
                $arrayTemp = explode('=', $item);

                if (!is_array($arrayTemp) || count($arrayTemp) <= 1) {
                    throw new \Exception(sprintf('Metric macro must have dimension and value: %s', $fieldsInBracketMetricMacro[$index]), Response::HTTP_BAD_REQUEST);
                }

                $arrayTemp[1] = $this->getSubMacrosValue($arrayTemp[1], $params);
                $newArraySubMetricMacroExpression[$arrayTemp[0]] = $arrayTemp[1];
            }

            try {
                $valueForExpression = $this->getValueMetricMacroByQuery($metricFieldName, $newArraySubMetricMacroExpression, $params);
                $expression = str_replace($fieldsInBracketMetricMacro[$index], $valueForExpression, $expression);
            } catch (\Exception $ex) {
                throw new \Exception(sprintf('Can not do expression to get calculated metric value with metric macro: %s', $fieldsInBracketMetricMacro[$index]), Response::HTTP_BAD_REQUEST);
            }
        }

        return $expression;
    }

    /**
     * @param $expression
     * @param ParamsInterface $params
     * @param $extractExpressionMetricVariables
     * @return mixed
     * @throws \Exception
     */
    private function doExpressionWithMetricVariable($expression, ParamsInterface $params, $extractExpressionMetricVariables)
    {
        if (!is_array($extractExpressionMetricVariables) || empty($extractExpressionMetricVariables)) {

            return $expression;
        }

        foreach ($extractExpressionMetricVariables as $extractExpressionMetricVariable) {
            $valueForExpression = (int)$this->getMacrosTimeValue($extractExpressionMetricVariable, $params);
            $expression = str_replace($extractExpressionMetricVariable, $valueForExpression, $expression);
            unset($valueForExpression);
        }

        return $expression;
    }

    /**
     * @param $expression
     * @param $extractExpressionMetrics
     * @param $calculatedMetrics
     * @param $showInTotals
     * @param $total
     * @return mixed
     */
    private function doExpressionWithMetrics($expression, $extractExpressionMetrics, $calculatedMetrics, $showInTotals, $total)
    {
        if (!is_array($extractExpressionMetrics) || empty($extractExpressionMetrics)) {

            return $expression;
        }

        foreach ($extractExpressionMetrics as $extractExpressionMetric) {

            $metricFieldNameValue = str_replace(['[', ']'], '', $extractExpressionMetric);
            // old value
            if (is_array($calculatedMetrics) && !empty($calculatedMetrics) && array_key_exists($metricFieldNameValue, $calculatedMetrics)) {
                $valueForExpression = $calculatedMetrics[$metricFieldNameValue];

                $expression = str_replace($extractExpressionMetric, $valueForExpression, $expression);
                unset($valueForExpression);

                continue;
            }

            // in $total compare with alias name in showInTotal to get original metrics
            $valueForExpression = $this->getValueMetricInTotal($expression, $showInTotals, $total, $metricFieldNameValue);

            $expression = str_replace($extractExpressionMetric, $valueForExpression, $expression);
        }

        return $expression;
    }

    /**
     * @param $column
     * @param $arraySubMetricMacroExpression
     * @param $params
     * @return mixed
     */
    private function getValueMetricMacroByQuery($column, $arraySubMetricMacroExpression, ParamsInterface $params)
    {
        return $this->sqlBuilder->executeQueryGetMetricValue($column, $arraySubMetricMacroExpression, $params);
    }

    /**
     * @param $expression
     * @return array
     */
    private function extract_dynamic_contents($expression)
    {
        // Extract metrics
        $pattern_metrics = '/\[(.*?)\]/m';
        preg_match_all($pattern_metrics, $expression,
            $out, PREG_SET_ORDER);

        $metrics = array_unique(array_map(function ($item) {
            return $item[0];
        }, $out));

        // extract macros
        $pattern_macro_names = '/\$\w+\s*\(/';
        preg_match_all($pattern_macro_names, $expression,
            $out, PREG_SET_ORDER);

        $macro_names = array_unique(array_map(function ($item) {

            return trim($item[0], ' (');

        }, $out));

        // extract variables
        $pattern_variables = '/\$\w+/';
        preg_match_all($pattern_variables, $expression,
            $out, PREG_SET_ORDER);

        $variables = array_unique(array_map(function ($item) {
            return $item[0];
        }, $out));

        $variables = array_diff($variables, $macro_names);

        return [
            self::EXPRESSION_VARIABLES => $variables,
            self::EXPRESSION_METRICS => $metrics,
            self::EXPRESSION_MACROS => $macro_names
        ];
    }

    /**
     * @param $metricMacrosName
     * @param $string
     * @return mixed
     */
    private function checkMetricMacroNameInString($metricMacrosName, $string)
    {
        $metricMacroName = '';
        foreach ($metricMacrosName as $extractExpressionMacro) {
            if (strpos($string, $extractExpressionMacro) !== false) {
                $metricMacroName = trim($extractExpressionMacro, '$');

                break;
            }
        }

        return $metricMacroName;
    }

    /**
     * @param $type
     * @param $value
     * @return float|int
     */
    private function getTrueValueByType($type, $value)
    {
        switch ($type) {
            case self::TYPE_NUMBER:

                return (int)$value;

            case self::TYPE_DECIMAL:

                return number_format((float)$value, 4, ".", "");

            default:
                return $value;
        }
    }

    /**
     * @param $calculatedMetricsObject
     * @param $calculatedMetrics
     * @return array
     */
    private function filterCalculatedMetricsResultWithIsVisible($calculatedMetricsObject, $calculatedMetrics)
    {
        if (!is_array($calculatedMetrics) || empty($calculatedMetrics)) {
            return [];
        }

        foreach ($calculatedMetricsObject as $calculatedMetric) {
            if (!$calculatedMetric instanceof AddCalculatedMetrics) {
                continue;
            }

            $fieldName = $calculatedMetric->getFieldName();
            $isVisible = $calculatedMetric->isVisible();
            if ($isVisible == true) {
                continue;
            }

            unset($calculatedMetrics[$fieldName]);
        }

        return ($calculatedMetrics);
    }

    /**
     * @inheritdoc
     */
    public function getMacrosTimeValue($macroName, ParamsInterface $params)
    {
        // initializing earliest possible datetime in php to compare
        $date = (new DateTime())->setTimestamp(0);
        // remove space character
        $macroName = trim($macroName);
        switch ($macroName) {
            case self::TODAY:
                $today = (date_create("now"))->setTime(0,0,0);
                $diffToday = date_diff($today, $date);

                return $diffToday->format("%a");

            case self::YESTERDAY:
                $yesterday = (date_create("yesterday"))->setTime(0,0,0);
                $diffYesterday = date_diff($yesterday, $date);

                return $diffYesterday->format("%a");

            case self::START_DATE:
                $startDate = $params->getStartDate();

                if (!$startDate instanceof \DateTime) {
                    return 0;
                }

                $diffYesterday = date_diff($startDate, $date);

                return $diffYesterday->format("%a");

            case self::END_DATE:
                $endDate = $params->getEndDate();

                if (!$endDate instanceof \DateTime) {
                    return 0;
                }

                $diffYesterday = date_diff($endDate, $date);

                return $diffYesterday->format("%a");
        }

        return 0;
    }

    /**
     * @inheritdoc
     */
    public function getSubMacrosValue($macroName, ParamsInterface $params)
    {
        // remove space character
        $macroName = trim($macroName);
        switch ($macroName) {
            case self::TODAY:
                $today = date_create("now");

                return $today->format("Y-m-d");

            case self::YESTERDAY:
                $yesterday = date_create("yesterday");

                return $yesterday->format("Y-m-d");

            case self::START_DATE:
                $startDate = $params->getStartDate();

                return $startDate instanceof \DateTime ? $startDate->format("Y-m-d") : $macroName;

            case self::END_DATE:
                $endDate = $params->getEndDate();

                return $endDate instanceof \DateTime ? $endDate->format("Y-m-d") : $macroName;

            default:
                return $macroName;
        }
    }
}