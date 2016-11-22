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

class ParamsBuilder implements ParamsBuilderInterface
{

    const DATA_SET_KEY = 'dataSets';
    const TRANSFORM_KEY = 'transforms';
    const JOIN_BY_KEY = 'joinBy';

    /**
     * @inheritdoc
     */
    public function buildFromArray(array $params)
    {
        $param = new Params();

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
                    $transformObjects[] = new GroupByTransform($transform);
                    break;
                case TransformInterface::COMPARISON_PERCENT_TRANSFORM:
                    $transformObjects[] = new ComparisonPercentTransform($transform);
                    break;
                case TransformInterface::SORT_TRANSFORM:
                    foreach ($transform[TransformInterface::FIELDS_TRANSFORM] as $sortField) {
                        $transformObjects[] = new SortByTransform($sortField);
                    }

                    break;
            }
        }

        return $transformObjects;
    }

    /**
     * @inheritdoc
     */
    public function buildFromReportView(ReportViewInterface $reportView)
    {
        $allDataSets = $reportView->getDataSets();
        $allTransformations = $reportView->getTransforms();
        $joinBy = $reportView->getJoinedFields();

        $dataSetObjects = $this->createDataSets($allDataSets);
        $transformationObjects = $this->createTransforms($allTransformations);
        $joinByObject = $joinBy;

        return new Params($dataSetObjects, $joinByObject, $transformationObjects);
    }

}