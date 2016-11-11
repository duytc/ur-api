<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\DataSets\DataSet;
use UR\Domain\DTO\Report\JoinBy\JoinBy;
use UR\Domain\DTO\Report\Params;
use UR\Domain\DTO\Report\Transforms\FormatDateTransform;
use UR\Domain\DTO\Report\Transforms\FormatNumberTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\SortByTransform;
use UR\Model\Core\ReportViewInterface;

class ParamsBuilder implements ParamsBuilderInterface
{
    /**
     * @inheritdoc
     */
    public function buildFromArray(array $params)
    {
        $dataSetObjects = [];
        $transformationObjects = [];
        $joinByObject = null;

        if (array_key_exists(ReportBuilderConstant::DATA_SET_KEY, $params)) {
            $allDataSets = $params[ReportBuilderConstant::DATA_SET_KEY];
            $convertDataSetsToObject = json_decode($allDataSets);
            $dataSetObjects = $this->createDataSetsObjects($convertDataSetsToObject);
        }

        if (array_key_exists(ReportBuilderConstant::TRANSFORMS_KEY, $params)) {
            $allTransformations = $params[ReportBuilderConstant::TRANSFORMS_KEY];

            $convertAllTransformations = json_decode($allTransformations);
            $transformationObjects = $this->createTransformationObjects($convertAllTransformations);
        }

        if (array_key_exists(ReportBuilderConstant::JOIN_BY_KEY, $params)) {
            $joinByObject = new JoinBy($params[ReportBuilderConstant::JOIN_BY_KEY]);
        }

        return new Params($dataSetObjects, $joinByObject, $transformationObjects);
    }

    protected function createDataSetsObjects(array $dataSets)
    {
        $dataSetObjects = [];
        foreach ($dataSets as $dataSet) {
            $dataSetObjects[] = new DataSet($dataSet->dataSet, $dataSet->dimensions, $dataSet->filters, $dataSet->metrics);
        }

        return $dataSetObjects;
    }

    /**
     * @param array $allTransformations
     * @return array
     */
    protected function createTransformationObjects(array $allTransformations)
    {
        $transformationObjects = [];
        foreach ($allTransformations as $transformation) {
            if ($transformation->{ReportBuilderConstant::TARGET_TRANSFORMATION_KEY} == ReportBuilderConstant::TARGET_TRANSFORMATION_SINGLED_VALUE) {
                if ($transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} == ReportBuilderConstant::DATE_FORMAT_TRANSFORMATION_VALUE) {
                    $transformationObjects[] = new FormatDateTransform (
                        $transformation->{ReportBuilderConstant::FROM_FORMAT_TRANSFORMATION_KEY},
                        $transformation->{ReportBuilderConstant::TO_FORMAT_TRANSFORMATION_KEY},
                        $transformation->{ReportBuilderConstant::FIELD_NAME_TRANSFORMATION_KEY},
                        $transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY},
                        $transformation->{ReportBuilderConstant::TARGET_TRANSFORMATION_KEY});
                }

                if ($transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} == ReportBuilderConstant::NUMBER_FORMAT_TRANSFORMATION_VALUE) {

                    $scale = property_exists($transformation,ReportBuilderConstant::SCALE_TRANSFORMATION_KEY)? $transformation->{ReportBuilderConstant::SCALE_TRANSFORMATION_KEY}:0;

                    $transformationObjects[] = new FormatNumberTransform(
                        $transformation->{ReportBuilderConstant::PREDICTION_TRANSFORMATION_KEY},
                        $scale,
                        $transformation->{ReportBuilderConstant::THOUSAND_SEPARATOR_TRANSFORMATION_KEY},
                        $transformation->{ReportBuilderConstant::FIELD_NAME_TRANSFORMATION_KEY},
                        $transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY},
                        $transformation->{ReportBuilderConstant::TARGET_TRANSFORMATION_KEY}
                    );
                }
            }

            if ($transformation->{ReportBuilderConstant::TARGET_TRANSFORMATION_KEY} == ReportBuilderConstant::TARGET_TRANSFORMATION_ALL_VALUE) {

                if ($transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} == ReportBuilderConstant::GROUP_BY_TRANSFORMATION_VALUE) {
                    $transformationObjects [] = new GroupByTransform(
                        $transformation->{ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE},
                        $transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY}

                    );
                }

                if ($transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} == ReportBuilderConstant::SORT_BY_TRANSFORMATION_VALUE) {
                    $transformationObjects [] = new SortByTransform(
                        $transformation->{ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE},
                        $transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY}
                    );
                }
            }

        }

        return $transformationObjects;
    }

    /**
     * @inheritdoc
     */
    public function buildFromReportView(ReportViewInterface $reportView)
    {
        $allDataSets = $reportView->getDataSets();
        $allTransformations = $reportView->getTransforms();
        $joinBy = $reportView->getJoinedFields();

        $dataSetObjects = $this->createDataSetsObjects($allDataSets);
        $transformationObjects = $this->createTransformationObjects($allTransformations);
        $joinByObject = new JoinBy($joinBy);

        return new Params($dataSetObjects, $joinByObject, $transformationObjects);
    }

}