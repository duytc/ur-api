<?php


namespace UR\Service\Report;


use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\Params;
use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Domain\DTO\Report\Transforms\AddFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\FormatDateTransform;
use UR\Domain\DTO\Report\Transforms\FormatNumberTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\SortByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DTO\Report\WeightedCalculation;

class ParamsBuilder implements ParamsBuilderInterface
{

    const DATA_SET_KEY = 'dataSets';
    const TRANSFORM_KEY = 'transforms';
    const JOIN_BY_KEY = 'joinBy';
    const WEIGHTED_CALCULATION_KEY = 'calculations';
    const MULTI_VIEW_KEY = 'multiView';
    const REPORT_VIEWS_KEY = 'reportViews';
    const FILTERS_KEY = 'filters';

    /**
     * @inheritdoc
     */
    public function buildFromArray(array $params)
    {
        $param = new Params();

        $multiView = false;
        if (array_key_exists(self::MULTI_VIEW_KEY, $params)) {
            $multiView = filter_var($params[self::MULTI_VIEW_KEY], FILTER_VALIDATE_BOOLEAN);
        }

        $param->setMultiView($multiView);

        if ($param->isMultiView()) {
            if (!array_key_exists(self::REPORT_VIEWS_KEY, $params) || empty($params[self::MULTI_VIEW_KEY])) {
                throw new InvalidArgumentException('multi view require at least one report view is selected');
            }

            $param->setReportViews(json_decode($params[self::REPORT_VIEWS_KEY]));

            if (array_key_exists(self::FILTERS_KEY, $params) && !empty($params[self::FILTERS_KEY])) {
                $param->setFilters($params[self::FILTERS_KEY]);
            }

            if (array_key_exists(self::TRANSFORM_KEY, $params) && !empty($params[self::TRANSFORM_KEY])) {
                $param->setTransforms($params[self::TRANSFORM_KEY]);
            }

            return $param;
        }

        if (array_key_exists(self::DATA_SET_KEY, $params) && !empty($params[self::DATA_SET_KEY])) {
            $dataSets = $this->createDataSets(json_decode($params[self::DATA_SET_KEY], true));
            $param->setDataSets($dataSets);
        }

        if (array_key_exists(self::TRANSFORM_KEY, $params) && !empty($params[self::TRANSFORM_KEY])) {
            $transforms = $this->createTransforms(json_decode($params[self::TRANSFORM_KEY], true));
            $param->setTransforms($transforms);
        }

        if (array_key_exists(self::JOIN_BY_KEY, $params) && !empty($params[self::JOIN_BY_KEY])) {
            $param->setJoinByFields($params[self::JOIN_BY_KEY]);
        }

        if (array_key_exists(self::WEIGHTED_CALCULATION_KEY, $params) && !empty($params[self::WEIGHTED_CALCULATION_KEY])) {
            $param->setWeightedCalculations(new WeightedCalculation(json_decode($params[self::WEIGHTED_CALCULATION_KEY], true)));
        }

        return $param;
    }

    protected function createDataSets(array $dataSets)
    {
        if (!is_array($dataSets)) {
            throw new InvalidArgumentException(sprintf('expect array, got %s', gettype($dataSets)));
        }

        $dataSetObjects = [];
        foreach ($dataSets as $dataSet) {
            if (!is_array($dataSet)) {
                throw new InvalidArgumentException(sprintf('expect array, got %s', gettype($dataSet)));
            }

            $dataSetObjects[] = new DataSet($dataSet);
        }

        return $dataSetObjects;
    }

    /**
     * @param array $transforms
     * @throws \Exception
     * @return array
     */
    protected function createTransforms(array $transforms)
    {
        $sortByInputObjects = [];
        $groupByInputObjects = [];
        $transformObjects = [];
        foreach ($transforms as $transform) {
            if (!array_key_exists(TransformInterface::TRANSFORM_TYPE_KEY, $transform)) {
                throw new InvalidArgumentException('"transformType" is missing');
            }

            switch ($transform[TransformInterface::TRANSFORM_TYPE_KEY]) {
                case TransformInterface::ADD_FIELD_TRANSFORM:
                    foreach ($transform[TransformInterface::FIELDS_TRANSFORM] as $addField) {
                        $transformObjects[] = new AddFieldTransform($addField);
                    }
                    break;
                case TransformInterface::ADD_CALCULATED_FIELD_TRANSFORM:
                    $expressionLanguage = new ExpressionLanguage();
                    foreach ($transform[TransformInterface::FIELDS_TRANSFORM] as $addField) {
                        $transformObjects[] = new AddCalculatedFieldTransform($expressionLanguage, $addField);
                    }
                    break;
                case TransformInterface::FORMAT_DATE_TRANSFORM:
                    $transformObjects[] = new FormatDateTransform($transform);
                    break;
                case TransformInterface::FORMAT_NUMBER_TRANSFORM:
                    $transformObjects[] = new FormatNumberTransform($transform);
                    break;
                case TransformInterface::GROUP_TRANSFORM:
                    foreach ($transform[TransformInterface::FIELDS_TRANSFORM] as $groupField) {
                        $groupByInputObjects [] = $groupField;
                    }
                    break;
                case TransformInterface::COMPARISON_PERCENT_TRANSFORM:
                    foreach ($transform[TransformInterface::FIELDS_TRANSFORM] as $comparisonField) {
                        $transformObjects[] = new ComparisonPercentTransform($comparisonField);
                    }
                    break;
                case TransformInterface::SORT_TRANSFORM:
                    foreach ($transform[TransformInterface::FIELDS_TRANSFORM] as $sortField) {
                        $sortByInputObjects[] = $sortField;
                    }
                    break;
            }
        }

        if (!empty ($sortByInputObjects)) {
            $transformObjects[] = new SortByTransform($sortByInputObjects);
        }

        if (!empty ($groupByInputObjects)) {
            $transformObjects[] = new GroupByTransform($groupByInputObjects);
        }

        return $transformObjects;
    }

    /**
     * @inheritdoc
     */
    public function buildFromReportView(ReportViewInterface $reportView)
    {
        $param = new Params();
        $param->setDataSets($this->createDataSets($reportView->getDataSets()))
            ->setTransforms($this->createTransforms($reportView->getTransforms()))
            ->setJoinByFields($reportView->getJoinBy())
            ->setWeightedCalculations(new WeightedCalculation($reportView->getWeightedCalculations()))
            ->setMultiView($reportView->isMultiView())
            ->setReportViews($reportView->getReportViews())
            ->setFilters($reportView->getFilters());

        return $param;
    }
}