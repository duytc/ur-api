<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\DataSets\DataSet;
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
        $joinByValue = null;

        if (array_key_exists(ReportBuilderConstant::DATA_SET_KEY, $params)) {
            $allDataSets = $params[ReportBuilderConstant::DATA_SET_KEY];
            $convertDataSetsToObject = json_decode($allDataSets);
            $dataSetObjects = $this->createDataSetsObjects($convertDataSetsToObject);
        }

        if (array_key_exists(ReportBuilderConstant::TRANSFORMS_KEY, $params)) {
            $allTransformations = $params[ReportBuilderConstant::TRANSFORMS_KEY];
            $convertAllTransformations = json_decode($allTransformations);
            $aggregatedTransformations = $this->transformMultipleGroupByAndSortByToOne($convertAllTransformations);
            $transformationObjects = $this->createTransformationObjects($aggregatedTransformations);
        }

        if (array_key_exists(ReportBuilderConstant::JOIN_BY_KEY, $params)) {
            $joinByValue = $params[ReportBuilderConstant::JOIN_BY_KEY];
        }

        return new Params($dataSetObjects, $joinByValue, $transformationObjects);
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
     * @param array $convertedTransformations
     * @return array
     * @throws \Exception
     */
    protected function transformMultipleGroupByAndSortByToOne(array $convertedTransformations)
    {
        $aggregatedGroupByObject = null;
        $aggregatedSortByObject = null;

        foreach ($convertedTransformations as $key => $transformation) {
            if (!property_exists($transformation, ReportBuilderConstant::TARGET_TRANSFORMATION_KEY) || !property_exists($transformation, ReportBuilderConstant::TYPE_TRANSFORMATION_KEY)) {
                throw new \Exception(sprintf('Transformations must have key = %s', ReportBuilderConstant::TARGET_TRANSFORMATION_KEY));
            }

            if ($transformation->{ReportBuilderConstant::TARGET_TRANSFORMATION_KEY} == ReportBuilderConstant::TARGET_TRANSFORMATION_ALL_VALUE) {
                if ($transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} == ReportBuilderConstant::GROUP_BY_TRANSFORMATION_VALUE) {

                    if (null == $aggregatedGroupByObject) {
                        $aggregatedGroupByObject = $transformation;
                        unset($convertedTransformations[$key]);
                        continue;
                    }

                    if (!property_exists($aggregatedGroupByObject, ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE) || !property_exists($transformation, ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE)) {
                        throw new \Exception(sprintf('No field for group  by in report builder'));
                    }

                    $allFields = array_merge($aggregatedGroupByObject->{ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE}, $transformation->{ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE});
                    $aggregatedGroupByObject->{ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE} = $allFields;
                    unset($convertedTransformations[$key]);
                    continue;

                }

                if ($transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} == ReportBuilderConstant::SORT_BY_TRANSFORMATION_VALUE) {

                    if (null == $aggregatedSortByObject) {
                        $aggregatedSortByObject = $transformation;
                        unset($convertedTransformations[$key]);
                        continue;
                    }

                    if (!property_exists($aggregatedSortByObject, ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE) || !property_exists($transformation, ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE)) {
                        throw new \Exception(sprintf('No field for sort by in report builder'));
                    }

                    $allFields = array_merge($aggregatedSortByObject->{ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE}, $transformation->{ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE});
                    $aggregatedSortByObject->{ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE} = $allFields;
                    unset($convertedTransformations[$key]);
                }
            }

        }

        if (null != $aggregatedGroupByObject) {
            array_push($convertedTransformations, $aggregatedGroupByObject);
        }

        if (null != $aggregatedSortByObject) {
            array_push($convertedTransformations, $aggregatedSortByObject);
        }

        return $convertedTransformations;
    }

    /**
     * @param array $allTransformations
     * @throws \Exception
     * @return array
     */
    protected function createTransformationObjects(array $allTransformations)
    {
        $transformationObjects = [];
        foreach ($allTransformations as $transformation) {
            if (!property_exists($transformation, ReportBuilderConstant::TARGET_TRANSFORMATION_KEY) || !property_exists($transformation, ReportBuilderConstant::TYPE_TRANSFORMATION_KEY)) {
                throw new \Exception(sprintf('Transformations must have key = %s', ReportBuilderConstant::TARGET_TRANSFORMATION_KEY));
            }

            if ($transformation->{ReportBuilderConstant::TARGET_TRANSFORMATION_KEY} == ReportBuilderConstant::TARGET_TRANSFORMATION_SINGLED_VALUE) {
                if ($transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} == ReportBuilderConstant::DATE_FORMAT_TRANSFORMATION_VALUE) {

                    $fromFormat = property_exists($transformation, ReportBuilderConstant::FROM_FORMAT_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::FROM_FORMAT_TRANSFORMATION_KEY} : null;
                    $toFormat = property_exists($transformation, ReportBuilderConstant::TO_FORMAT_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::TO_FORMAT_TRANSFORMATION_KEY} : null;
                    $fieldName = property_exists($transformation, ReportBuilderConstant::FIELD_NAME_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::FIELD_NAME_TRANSFORMATION_KEY} : null;
                    $typeTransformation = property_exists($transformation, ReportBuilderConstant::TYPE_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} : null;
                    $targetTransformation = property_exists($transformation, ReportBuilderConstant::TARGET_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::TARGET_TRANSFORMATION_KEY} : null;

                    $transformationObjects[] = new FormatDateTransform ($fromFormat, $toFormat, $fieldName, $typeTransformation, $targetTransformation);
                }

                if ($transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} == ReportBuilderConstant::NUMBER_FORMAT_TRANSFORMATION_VALUE) {

                    $prediction = property_exists($transformation, ReportBuilderConstant::PREDICTION_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::PREDICTION_TRANSFORMATION_KEY} : null;
                    $scale = property_exists($transformation, ReportBuilderConstant::SCALE_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::SCALE_TRANSFORMATION_KEY} : null;
                    $thousandSeparator = property_exists($transformation, ReportBuilderConstant::THOUSAND_SEPARATOR_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::THOUSAND_SEPARATOR_TRANSFORMATION_KEY} : null;
                    $fieldNameTransformation = property_exists($transformation, ReportBuilderConstant::FIELD_NAME_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::FIELD_NAME_TRANSFORMATION_KEY} : null;
                    $targetTransformation = property_exists($transformation, ReportBuilderConstant::TARGET_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::TARGET_TRANSFORMATION_KEY} : null;

                    $transformationObjects[] = new FormatNumberTransform($prediction, $scale, $thousandSeparator, $fieldNameTransformation,
                        $fieldNameTransformation, $targetTransformation);
                }
            }

            if ($transformation->{ReportBuilderConstant::TARGET_TRANSFORMATION_KEY} == ReportBuilderConstant::TARGET_TRANSFORMATION_ALL_VALUE) {
                if ($transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} == ReportBuilderConstant::GROUP_BY_TRANSFORMATION_VALUE) {

                    $fieldGroup = property_exists($transformation, ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE) ?
                        $transformation->{ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE} : null;
                    $typeTransformation = property_exists($transformation, ReportBuilderConstant::TYPE_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} : null;
                    $targetTransformation = property_exists($transformation, ReportBuilderConstant::TARGET_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::TARGET_TRANSFORMATION_KEY} : null;

                    $transformationObjects [] = new GroupByTransform($fieldGroup, $typeTransformation, $targetTransformation);
                }

                if ($transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} == ReportBuilderConstant::SORT_BY_TRANSFORMATION_VALUE) {

                    $fieldGroup = property_exists($transformation, ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE) ?
                        $transformation->{ReportBuilderConstant::FIELDS_GROUP_BY_TRANSFORMATION_VALUE} : null;
                    $typeTransformation = property_exists($transformation, ReportBuilderConstant::TYPE_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::TYPE_TRANSFORMATION_KEY} : null;
                    $targetTransformation = property_exists($transformation, ReportBuilderConstant::TARGET_TRANSFORMATION_KEY) ?
                        $transformation->{ReportBuilderConstant::TARGET_TRANSFORMATION_KEY} : null;

                    $transformationObjects [] = new SortByTransform($fieldGroup, $typeTransformation, $targetTransformation);
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
        $joinByObject = $joinBy;

        return new Params($dataSetObjects, $joinByObject, $transformationObjects);
    }

}