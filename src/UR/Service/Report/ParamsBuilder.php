<?php


namespace UR\Service\Report;


use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\Formats\ColumnPositionFormat;
use UR\Domain\DTO\Report\Formats\CurrencyFormat;
use UR\Domain\DTO\Report\Formats\DateFormat;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\Formats\NumberFormat;
use UR\Domain\DTO\Report\Params;
use UR\Domain\DTO\Report\ReportViews\ReportView;
use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Domain\DTO\Report\Transforms\AddFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\SortByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\ReportViewInterface;
use UR\Service\DTO\Report\WeightedCalculation;

class ParamsBuilder implements ParamsBuilderInterface
{
    const DATA_SET_KEY = 'dataSets';
    const FIELD_TYPES_KEY = 'fieldTypes';
    const TRANSFORM_KEY = 'transforms';
    const JOIN_BY_KEY = 'joinBy';
    const WEIGHTED_CALCULATION_KEY = 'weightedCalculations';
    const MULTI_VIEW_KEY = 'multiView';
    const REPORT_VIEWS_KEY = 'reportViews';
    const FILTERS_KEY = 'filters';
    const FORMAT_KEY = 'formats';
    const SHOW_IN_TOTAL_KEY = 'showInTotal';
    const SUB_REPORT_INCLUDED_KEY = 'subReportsIncluded';

    /**
     * @inheritdoc
     */
    public function buildFromArray(array $data)
    {
        $param = new Params();

        $multiView = false;
        if (array_key_exists(self::MULTI_VIEW_KEY, $data)) {
            $multiView = filter_var($data[self::MULTI_VIEW_KEY], FILTER_VALIDATE_BOOLEAN);
        }

        $param->setMultiView($multiView);
        $param->setSubReportIncluded(false);

        if ($param->isMultiView()) {
            if (!array_key_exists(self::REPORT_VIEWS_KEY, $data) || empty($data[self::MULTI_VIEW_KEY])) {
                throw new InvalidArgumentException('multi view require at least one report view is selected');
            }

            if (!empty($data[self::REPORT_VIEWS_KEY])) {
                $reportViews = $this->createReportViews(json_decode($data[self::REPORT_VIEWS_KEY], true));
                $param->setReportViews($reportViews);
            }

            if (array_key_exists(self::SUB_REPORT_INCLUDED_KEY, $data)) {
                $param->setSubReportIncluded(filter_var($data[self::SUB_REPORT_INCLUDED_KEY], FILTER_VALIDATE_BOOLEAN));
            }

        } else {
            if (array_key_exists(self::DATA_SET_KEY, $data) && !empty($data[self::DATA_SET_KEY])) {
                $dataSets = $this->createDataSets(json_decode($data[self::DATA_SET_KEY], true));
                $param->setDataSets($dataSets);
            }

            if (array_key_exists(self::JOIN_BY_KEY, $data) && !empty($data[self::JOIN_BY_KEY])) {
                $param->setJoinByFields($data[self::JOIN_BY_KEY]);
            }
        }

        if (array_key_exists(self::TRANSFORM_KEY, $data) && !empty($data[self::TRANSFORM_KEY])) {
            $transforms = $this->createTransforms(json_decode($data[self::TRANSFORM_KEY], true));
            $param->setTransforms($transforms);
        }


        if (array_key_exists(self::WEIGHTED_CALCULATION_KEY, $data)) {
            $calculations = json_decode($data[self::WEIGHTED_CALCULATION_KEY], true);
            if (!empty($calculations)) {
                $param->setWeightedCalculations(new WeightedCalculation($calculations));
            }
        }

        if (array_key_exists(self::SHOW_IN_TOTAL_KEY, $data)) {
            $param->setShowInTotal(json_decode($data[self::SHOW_IN_TOTAL_KEY], true));
        }

        if (array_key_exists(self::FIELD_TYPES_KEY, $data)) {
            $param->setFieldTypes(json_decode($data[self::FIELD_TYPES_KEY], true));
        }

        /* set output formatting */
        if (array_key_exists(self::FORMAT_KEY, $data) && !empty($data[self::FORMAT_KEY])) {
            $formats = $this->createFormats(json_decode($data[self::FORMAT_KEY], true));
            $param->setFormats($formats);
        }

        return $param;
    }

    protected function createDataSets(array $dataSets)
    {
        $dataSetObjects = [];
        foreach ($dataSets as $dataSet) {
            if (!is_array($dataSet)) {
                throw new InvalidArgumentException(sprintf('expect array, got %s', gettype($dataSet)));
            }

            $dataSetObjects[] = new DataSet($dataSet);
        }

        return $dataSetObjects;
    }

    public static function createReportViews(array $reportViews)
    {
        $reportViewObjects = [];
        foreach ($reportViews as $reportView) {
            if (!is_array($reportView)) {
                throw new InvalidArgumentException(sprintf('expect array, got %s', gettype($reportView)));
            }

            $reportViewObjects[] = new ReportView($reportView);
        }

        return $reportViewObjects;
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
     * @param array $formats
     * @throws \Exception
     * @return array
     */
    protected function createFormats(array $formats)
    {
        $formatObjects = [];

        foreach ($formats as $format) {
            if (!array_key_exists(FormatInterface::FORMAT_TYPE_KEY, $format)) {
                throw new InvalidArgumentException('format "type" is missing');
            }

            switch ($format[FormatInterface::FORMAT_TYPE_KEY]) {
                case FormatInterface::FORMAT_TYPE_DATE:
                    $formatObjects[] = new DateFormat($format);

                    break;

                case FormatInterface::FORMAT_TYPE_NUMBER:
                    $formatObjects[] = new NumberFormat($format);

                    break;
                
                case FormatInterface::FORMAT_TYPE_CURRENCY:
                    $formatObjects[] = new CurrencyFormat($format);

                    break;

                case FormatInterface::FORMAT_TYPE_COLUMN_POSITION:
                    $formatObjects[] = new ColumnPositionFormat($format);

                    break;
            }
        }

        return $formatObjects;
    }

    /**
     * @inheritdoc
     */
    public function buildFromReportView(ReportViewInterface $reportView)
    {
        $param = new Params();

        if ($reportView->isMultiView()) {
            $param
                ->setTransforms($this->createTransforms($reportView->getTransforms()))
                ->setMultiView($reportView->isMultiView())
                ->setReportViews($this->createReportViews($reportView->getReportViews()))
                ->setFilters($reportView->getFilters())
                ->setShowInTotal($reportView->getShowInTotal())
                ->setFieldTypes($reportView->getFieldTypes())
                ->setSubReportIncluded($reportView->isSubReportsIncluded())
            ;
        } else {
            $param
                ->setDataSets($this->createDataSets($reportView->getDataSets()))
                ->setTransforms($this->createTransforms($reportView->getTransforms()))
                ->setJoinByFields($reportView->getJoinBy())
                ->setMultiView($reportView->isMultiView())
                ->setShowInTotal($reportView->getShowInTotal())
                ->setFieldTypes($reportView->getFieldTypes())
            ;
        }

        if (is_array($reportView->getWeightedCalculations())) {
            $param->setWeightedCalculations(new WeightedCalculation($reportView->getWeightedCalculations()));
        }

        if (is_array($reportView->getFormats())) {
            $param->setFormats($this->createFormats($reportView->getFormats()));
        }

        return $param;
    }
}