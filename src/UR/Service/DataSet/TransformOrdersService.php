<?php


namespace UR\Service\DataSet;

use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Parser\Transformer\Collection\AddCalculatedField;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Parser\Transformer\Collection\Augmentation;
use UR\Service\Parser\Transformer\Collection\ComparisonPercent;
use UR\Service\Parser\Transformer\Collection\ConvertCase;
use UR\Service\Parser\Transformer\Collection\ExtractPattern;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\NormalizeText;
use UR\Service\Parser\Transformer\Collection\ReplaceText;
use UR\Service\Parser\Transformer\Collection\SortByColumns;
use UR\Service\Parser\Transformer\Collection\SubsetGroup;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Column\NumberFormat;

class TransformOrdersService implements TransformOrdersServiceInterface
{
    /**
     * @inheritdoc
     */
    public function orderTransforms(array $transforms, ConnectedDataSourceInterface $connectedDataSource, $index = 0)
    {
        $orderTransforms = [];
        $visibleFields = $this->getVisibleFields($connectedDataSource);

        foreach ($transforms as $key => $transform) {
            if ($key < $index) {
                $orderTransforms[] = $transform;
                $visibleFields = $this->updateVisibleFields($visibleFields, $transform);
                continue;
            }

            if (!$this->isValidTransform($transform, $visibleFields)) {
                /** Do not reorder transform if waited transform in the end. It make loop limit */
                if ($key == 0 || ($key < count($transforms) - 1 && $key >= $index)) {
                    /** Ensure all Number Formats is last */
                    if ($transform instanceof GroupByColumns || $transform instanceof SubsetGroup) {
                        if ($this->isAfterNumberFormats($transforms, $key)) {
                            /** Move waited transform to end of queue */
                            $transforms[] = $transform;
                            unset($transforms[$key]);
                            $transforms = array_values($transforms);

                            /** Restart order transforms with update queue */
                            return $this->orderTransforms($transforms, $connectedDataSource, $key);
                        }

                        $visibleFields = $this->updateVisibleFields($visibleFields, $transform);
                        $orderTransforms[] = $transform;
                    } else {
                        /** Move waited transform to end of queue */
                        $transforms[] = $transform;
                        unset($transforms[$key]);
                        $transforms = array_values($transforms);

                        /** Restart order transforms with update queue */
                        return $this->orderTransforms($transforms, $connectedDataSource, $key);
                    }
                } else {
                    $visibleFields = $this->updateVisibleFields($visibleFields, $transform);
                    $orderTransforms[] = $transform;
                }
            } else {
                $visibleFields = $this->updateVisibleFields($visibleFields, $transform);
                $orderTransforms[] = $transform;
            }
        }

        return $orderTransforms;
    }

    /**
     * @param ConnectedDataSourceInterface $connectedDataSource
     * @return array
     */
    private function getVisibleFields(ConnectedDataSourceInterface $connectedDataSource)
    {
        $visibleFields = [];
        $mapFields = $connectedDataSource->getMapFields();
        if (!is_array($mapFields)) {
            $mapFields = [];
        }

        $dataSource = $connectedDataSource->getDataSource();
        if ($dataSource instanceof DataSourceInterface) {
            $detectFields = $dataSource->getDetectedFields();
            if (!is_array($detectFields)) {
                $detectFields = [];
            }

            foreach ($detectFields as $detectField => $index) {
                $visibleFields[] = sprintf("__\$\$FILE\$\$%s", $detectField);
            }
        }

        return array_values(array_merge($mapFields, $visibleFields));
    }

    /**
     * @param $transform
     * @param array $visibleFields
     * @return bool
     */
    private function isValidTransform($transform, array $visibleFields)
    {
        if ($transform instanceof AddField) {
            return $this->evaluateAddFieldTransform($transform, $visibleFields);
        }

        if ($transform instanceof DateFormat) {
            return $this->evaluateDateFormatTransform($transform, $visibleFields);
        }

        if ($transform instanceof NumberFormat) {
            return false;
        }

        if ($transform instanceof ReplaceText) {
            return $this->evaluateReplaceTextTransform($transform, $visibleFields);
        }

        if ($transform instanceof ExtractPattern) {
            return $this->evaluateExtractPatternTransform($transform, $visibleFields);
        }

        if ($transform instanceof ConvertCase) {
            return $this->evaluateConvertCaseTransform($transform, $visibleFields);
        }

        if ($transform instanceof NormalizeText) {
            return $this->evaluateNormalizeTextTransform($transform, $visibleFields);
        }

        if ($transform instanceof ComparisonPercent) {
            return $this->evaluateComparisonPercentTransform($transform, $visibleFields);
        }

        if ($transform instanceof AddCalculatedField) {
            return $this->evaluateAddCalculatedFieldTransform($transform, $visibleFields);
        }

        if ($transform instanceof GroupByColumns) {
            return $this->evaluateGroupByColumnsTransform($transform, $visibleFields);
        }

        if ($transform instanceof SortByColumns) {
            return true;
        }

        if ($transform instanceof Augmentation) {
            return $this->evaluateAugmentationTransform($transform, $visibleFields);
        }

        if ($transform instanceof SubsetGroup) {
            return $this->evaluateSubsetGroupTransform($transform, $visibleFields);
        }

        return false;
    }

    /**
     * @param AddField $transform
     * @param array $visibleFields
     * @return bool
     */
    private function evaluateAddFieldTransform(AddField $transform, array $visibleFields)
    {
        if (in_array($transform->getType(), [FieldType::NUMBER, FieldType::DECIMAL, FieldType::DATE, FieldType::DATETIME])) {
            return true;
        }

        /** For text, large text */
        $expression = $transform->getTransformValue();
        $regex = '/\[(.*?)\]/';
        if (!preg_match_all($regex, $expression, $matches)) {
            return true;
        };

        $fields = $matches[1];

        foreach ($fields as $field) {
            if (!in_array($field, $visibleFields)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param DateFormat $transform
     * @param array $visibleFields
     * @return bool
     */
    private function evaluateDateFormatTransform(DateFormat $transform, array $visibleFields)
    {
        $field = $transform->getField();

        return in_array($field, $visibleFields);
    }

    /**
     * @param ReplaceText $transform
     * @param array $visibleFields
     * @return bool
     */
    private function evaluateReplaceTextTransform(ReplaceText $transform, array $visibleFields)
    {
        $field = $transform->getField();
        return in_array($field, $visibleFields);
    }

    /**
     * @param ExtractPattern $transform
     * @param array $visibleFields
     * @return bool
     */
    private function evaluateExtractPatternTransform(ExtractPattern $transform, array $visibleFields)
    {
        $field = $transform->getField();
        return in_array($field, $visibleFields);
    }

    /**
     * @param ConvertCase $transform
     * @param array $visibleFields
     * @return bool
     */
    private function evaluateConvertCaseTransform(ConvertCase $transform, array $visibleFields)
    {
        $field = $transform->getField();
        return in_array($field, $visibleFields);
    }

    /**
     * @param NormalizeText $transform
     * @param array $visibleFields
     * @return bool
     */
    private function evaluateNormalizeTextTransform(NormalizeText $transform, array $visibleFields)
    {
        $field = $transform->getField();
        return in_array($field, $visibleFields);
    }

    /**
     * @param ComparisonPercent $transform
     * @param array $visibleFields
     * @return bool
     */
    private function evaluateComparisonPercentTransform(ComparisonPercent $transform, array $visibleFields)
    {
        $firstField = $transform->getNumerator();
        $secondField = $transform->getDenominator();

        return in_array($firstField, $visibleFields) && in_array($secondField, $visibleFields);
    }

    /**
     * @param Augmentation $transform
     * @param array $visibleFields
     * @return bool
     */
    private function evaluateAugmentationTransform(Augmentation $transform, array $visibleFields)
    {
        $mapConditions = $transform->getMapConditions();

        foreach ($mapConditions as $map) {
            if (!array_key_exists(Augmentation::DATA_SOURCE_SIDE, $map)) {
                continue;
            }

            $left = $map[Augmentation::DATA_SOURCE_SIDE];

            if (!in_array($left, $visibleFields)) {
                return false;
            }
        }

        $customConditions = $transform->getCustomConditions();
        foreach ($customConditions as $customCondition) {
            if (!array_key_exists(Augmentation::CUSTOM_FIELD_KEY, $customCondition)) {
                continue;
            }

            $field = $customCondition[Augmentation::CUSTOM_FIELD_KEY];

            if (!in_array($field, $visibleFields)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param GroupByColumns $transform
     * @param array $visibleFields
     * @return bool
     */
    private function evaluateGroupByColumnsTransform(GroupByColumns $transform, array $visibleFields)
    {
        $groupFields = $transform->getGroupByColumns();

        foreach ($groupFields as $field) {
            if (!in_array($field,$visibleFields)) {
                return false;
            }
        }

        if ($transform->isAggregateAll()) {
            return false;
        } else {
            $sumFields = $transform->getAggregationFields();
            foreach ($sumFields as $field) {
                if (!in_array($field,$visibleFields)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param SubsetGroup $transform
     * @param array $visibleFields
     * @return bool
     */
    private function evaluateSubsetGroupTransform(SubsetGroup $transform, array $visibleFields)
    {
        $groupFields = $transform->getGroupFields();

        foreach ($groupFields as $field) {
            if (!in_array($field,$visibleFields)) {
                return false;
            }
        }

        $sumFields = [];
        if ($transform->isAggregateAll()) {
            $mapFields = $transform->getMapFields();

            foreach ($mapFields as $mapField) {
                if (!array_key_exists(SubsetGroup::GROUP_DATA_SET_SIDE, $mapField)) {
                    continue;
                }

                $sumFields[] = $mapField[SubsetGroup::GROUP_DATA_SET_SIDE];
            }
        } else {
            $sumFields = $transform->getAggregationFields();
        }

        foreach ($sumFields as $field) {
            if (!in_array($field,$visibleFields)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param AddCalculatedField $transform
     * @param array $visibleFields
     * @return bool
     */
    private function evaluateAddCalculatedFieldTransform(AddCalculatedField $transform, array $visibleFields)
    {
        $defaultValues = $transform->getDefaultValues();
        if (!is_array($defaultValues)) {
            $defaultValues = [];
        }

        foreach ($defaultValues as $item) {
            $field = $item[AddCalculatedField::CONDITION_FIELD_KEY];

            if ($field != AddCalculatedField::CONDITION_FIELD_CALCULATED_VALUE && !in_array($field, $visibleFields)) {
                return false;
            }
        }

        $expression = $transform->getExpression();
        $regex = '/\[(.*?)\]/';
        if (!preg_match_all($regex, $expression, $matches)) {
            return true;
        };

        $fields = $matches[1];

        foreach ($fields as $field) {
            if (!in_array($field, $visibleFields)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array $visibleFields
     * @param $transform
     * @return array
     */
    private function updateVisibleFields(array $visibleFields, $transform)
    {
        if ($transform instanceof AddField) {
            $visibleFields[] = $transform->getColumn();
        }

        if ($transform instanceof DateFormat) {
            $visibleFields[] = $transform->getField();
        }

        if ($transform instanceof NumberFormat) {
            $visibleFields[] = $transform->getField();
        }

        if ($transform instanceof ReplaceText) {
            if ($transform->isIsOverride()) {
                $visibleFields[] = $transform->getField();
            } else {
                $visibleFields[] = $transform->getTargetField();
            }
        }

        if ($transform instanceof ExtractPattern) {
            if ($transform->isIsOverride()) {
                $visibleFields[] = $transform->getField();
            } else {
                $visibleFields[] = $transform->getTargetField();
            }
        }

        if ($transform instanceof ConvertCase) {
            if ($transform->isIsOverride()) {
                $visibleFields[] = $transform->getField();
            } else {
                $visibleFields[] = $transform->getTargetField();
            }
        }

        if ($transform instanceof NormalizeText) {
            if ($transform->isIsOverride()) {
                $visibleFields[] = $transform->getField();
            } else {
                $visibleFields[] = $transform->getTargetField();
            }
        }

        if ($transform instanceof ComparisonPercent) {
            $visibleFields[] = $transform->getNewColumn();
        }

        if ($transform instanceof AddCalculatedField) {
            $visibleFields[] = $transform->getColumn();
        }

        if ($transform instanceof GroupByColumns) {
        }

        if ($transform instanceof SortByColumns) {
        }

        if ($transform instanceof Augmentation) {
            $mapFields = $transform->getMapFields();

            foreach ($mapFields as $map) {
                if (!array_key_exists(Augmentation::DATA_SOURCE_SIDE, $map)) {
                    continue;
                }

                $visibleFields[] = $map[Augmentation::DATA_SOURCE_SIDE];
            }
        }

        if ($transform instanceof SubsetGroup) {
        }

        return $visibleFields;
    }

    /**
     * @param $transforms
     * @param $key
     * @return bool
     */
    private function isAfterNumberFormats($transforms, $key)
    {
        for ($i = $key + 1; $i < count($transforms); $i++) {
            if (!$transforms[$i] instanceof NumberFormat) {
                return false;
            }
        }

        return true;
    }
}