<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\Filters\DateFilter;
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

    public function buildFromArray(array $params)
    {
        $filterObjects = [];
        $transformationObjects = [];

        $allFilters = $params[ReportBuilderConstant::FILTERS_KEY];
        foreach ($allFilters as $filter) {
            if ($filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY] == ReportBuilderConstant::DATE_FIELD_TYPE_FILTER_KEY) {
                $filterObjects[]=  new DateFilter(
                    $filter[ReportBuilderConstant::FIELD_NAME_KEY],
                    $filter[ReportBuilderConstant::FIELD_TYPE_FILTER_KEY],
                    $filter[ReportBuilderConstant::DATE_FORMAT_FILTER_KEY],
                    $filter[ReportBuilderConstant::DATE_RANGE_FILTER_KEY]
                );
            }

            if ( ReportBuilderConstant::FIELD_TYPE_FILTER_KEY == ReportBuilderConstant::TEXT_FIELD_TYPE_FILTER_KEY ) {
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

        $allTransformations = $params[ReportBuilderConstant::TRANSFORMS_KEY];
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

        $this->filters = $filterObjects;
        $this->transformations = $transformationObjects;
    }

    public function buildFromReportView(ReportViewInterface $reportView)
    {
        // TODO: Implement buildFromReportView() method.
    }
}