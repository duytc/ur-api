<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\Filters\DateFilter;
use UR\Domain\DTO\Report\JoinBy\JoinBy;
use UR\Domain\DTO\Report\Transforms\FormatDateTransform;
use UR\Domain\DTO\Report\Transforms\FormatNumberTransform;
use UR\Model\Core\ReportViewInterface;
use UR\Service\Parser\Filter\NumberFilter;
use UR\Service\Parser\Filter\TextFilter;

class ParamsBuilder implements ParamsBuilderInterface
{

    protected $filters;
    protected $transformations;
    protected $joinBy;

    /** @inheritdoc */
    public function getFiltersByDataSet($dataSetId)
    {
        return $this->filters;
    }

    /**
     * @inheritdoc
     */
    public function getMetricsByDataSet($dataSetId)
    {

    }

    /**
     * @inheritdoc
     */
    public function getDimensionByDataSet($dataSetId)
    {

    }
    /**
     * @inheritdoc
     */
    public function getJoinBy()
    {
        return $this->joinBy;
    }

    /**
     * @inheritdoc
     */
    public function getTransformations()
    {
        return $this->transformations;
    }

    /**
    * @inheritdoc
    */
    public function buildFromArray(array $params)
    {
        $filterObjects = [];
        $transformationObjects = [];
        $joinByObject = null;

        if (array_key_exists(ReportBuilderConstant::FILTERS_KEY, $params)) {
            $allFilters = $params[ReportBuilderConstant::FILTERS_KEY];
            $filterObjects = $this->createFilterObjects($allFilters);
        }

        if (array_key_exists(ReportBuilderConstant::TRANSFORMS_KEY, $params)) {
            $allTransformations = $params[ReportBuilderConstant::TRANSFORMS_KEY];
            $this->createTransformationObjects($allTransformations);
        }

        if (array_key_exists(ReportBuilderConstant::JOIN_BY_KEY, $params)) {
            $joinByObject =  new JoinBy($params[ReportBuilderConstant::JOIN_BY_KEY]);
        }

        $this->filters = $filterObjects;
        $this->transformations = $transformationObjects;
        $this->joinBy = $joinByObject;
    }

    /**
     * @inheritdoc
     */
    public function buildFromReportView(ReportViewInterface $reportView)
    {
        $allFilters = $reportView->getFilters();
        $allTransformations = $reportView->getTransforms();
        $joinBy = $reportView->getJoinedFields();

        $this->filters = $this->createFilterObjects($allFilters);
        $this->transformations = $this->createTransformationObjects($allTransformations);
        $this->joinBy = new JoinBy($joinBy);
    }

    /**
     * @param array $allFilters
     * @return array
     */
    protected function createFilterObjects(array $allFilters) {
        $filterObjects = [];
        foreach ($allFilters as $filter) {
            if ($filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY] == ReportBuilderConstant::DATE_FIELD_TYPE_FILTER_KEY) {
                $filterObjects[]=  new DateFilter(
                    $filter[ReportBuilderConstant::FIELD_NAME_KEY],
                    $filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY],
                    $filter[ReportBuilderConstant::DATE_FORMAT_FILTER_KEY],
                    $filter[ReportBuilderConstant::DATE_RANGE_FILTER_KEY]
                );
            }

            if (ReportBuilderConstant::FIELD_TYPE_FILTER_KEY == ReportBuilderConstant::TEXT_FIELD_TYPE_FILTER_KEY) {
                $filterObjects[] =  new TextFilter(
                    $filter[ReportBuilderConstant::FIELD_NAME_KEY],
                    $filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY],
                    $filter[ReportBuilderConstant::COMPARISON_TYPE_FILTER_KEY],
                    $filter[ReportBuilderConstant::COMPARISON_VALUE_FILTER_KEY]
                );
            }

            if ($filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY] == ReportBuilderConstant::NUMBER_FIELD_TYPE_FILTER_KEY) {
                $filterObjects[] =  new NumberFilter(
                    $filter[ReportBuilderConstant::FIELD_NAME_KEY],
                    $filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY],
                    $filter[ReportBuilderConstant::COMPARISON_TYPE_FILTER_KEY],
                    $filter[ReportBuilderConstant::COMPARISON_VALUE_FILTER_KEY]
                );
            }
        }

        return $filterObjects;
    }

    /**
     * @param array $allTransformations
     * @return array
     */
    protected function createTransformationObjects(array $allTransformations)
    {
        $transformationObjects = [];
        foreach ($allTransformations as $transformation) {
            if ($transformation[ReportBuilderConstant::TARGET_TRANSFORMATION_KEY] == ReportBuilderConstant::TARGET_TRANSFORMATION_SINGLED_VALUE) {
                if ($transformation[ReportBuilderConstant::TYPE_TRANSFORMATION_KEY] == ReportBuilderConstant::DATE_FORMAT_TRANSFORMATION_VALUE) {
                    $transformationObjects[] = new FormatDateTransform (
                        $transformation[ReportBuilderConstant::FROM_FORMAT_TRANSFORMATION_KEY],
                        $transformation[ReportBuilderConstant::TO_FORMAT_TRANSFORMATION_KEY],
                        $transformation[ReportBuilderConstant::FIELD_NAME_TRANSFORMATION_KEY],
                        $transformation[ReportBuilderConstant::TYPE_TRANSFORMATION_KEY],
                        $transformation[ReportBuilderConstant::TARGET_TRANSFORMATION_KEY]);
                }

                if ($transformation[ReportBuilderConstant::TYPE_TRANSFORMATION_KEY] == ReportBuilderConstant::NUMBER_FORMAT_TRANSFORMATION_VALUE) {
                    $transformationObjects[] =  new FormatNumberTransform(
                        $transformation[ReportBuilderConstant::PREDICTION_TRANSFORMATION_KEY],
                        $transformation[ReportBuilderConstant::SCALE_TRANSFORMATION_KEY],
                        $transformation[ReportBuilderConstant::THOUSAND_SEPARATOR_TRANSFORMATION_KEY],
                        $transformation[ReportBuilderConstant::FIELD_NAME_TRANSFORMATION_KEY],
                        $transformation[ReportBuilderConstant::TYPE_TRANSFORMATION_KEY],
                        $transformation[ReportBuilderConstant::TARGET_TRANSFORMATION_KEY]
                    );
                }
            }
        }

        return $transformationObjects;
    }

}