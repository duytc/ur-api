<?php


namespace UR\Bundle\ApiBundle\Behaviors;


use UR\Domain\DTO\Report\Formats\AbstractFormat;
use UR\Domain\DTO\Report\Formats\FormatInterface;
use UR\Domain\DTO\Report\Transforms\AddCalculatedFieldTransform;
use UR\Domain\DTO\Report\Transforms\ComparisonPercentTransform;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Domain\DTO\Report\Transforms\ReplaceTextTransform;
use UR\Domain\DTO\Report\Transforms\SortByTransform;
use UR\Domain\DTO\Report\Transforms\TransformInterface;

trait UpdateReportViewTrait
{
    protected function removeItemsFromArray(array $originalItems, array $removingItems)
    {
        foreach($originalItems as $i=>$item) {
            if (in_array($item, $removingItems)) {
                unset($originalItems[$i]);
            }
        }

        return $originalItems;
    }

    /**
     * @param array $format
     * @param array $allFields
     * @return array|null
     */
    protected function refreshFormat(array $format, array $allFields)
    {
        if (!array_key_exists(FormatInterface::FORMAT_TYPE_KEY, $format)) {
            return $format;
        }

        if (!array_key_exists(AbstractFormat::FIELDS_NAME_KEY, $format)) {
            return null;
        }
        $fields = $format[AbstractFormat::FIELDS_NAME_KEY];
        $missingFields = array_diff($fields, $allFields);
        $fields = $this->removeItemsFromArray($fields, $missingFields);

        if (empty($fields)) {
            return null;
        }

        $format[AbstractFormat::FIELDS_NAME_KEY] = $fields;
        return $format;
    }

    /**
     * @param array $transform
     * @param array $allFields
     * @return array|null
     */
    protected function refreshTransform(array $transform, array $allFields)
    {
        if (!array_key_exists(TransformInterface::TRANSFORM_TYPE_KEY, $transform)) {
            return $transform;
        }

        $type = $transform[TransformInterface::TRANSFORM_TYPE_KEY];
        switch($type) {
            case TransformInterface::GROUP_TRANSFORM;
                $groupFields = $transform[GroupByTransform::FIELDS_KEY];
                $fieldDiff = array_diff($groupFields, $allFields);
                $groupFields = $this->removeItemsFromArray($groupFields, $fieldDiff);
                if (empty($groupFields)) {
                    return null;
                }

                $transform[GroupByTransform::FIELDS_KEY] = $groupFields;
                return $transform;

            case TransformInterface::SORT_TRANSFORM;
                $ascFields = $transform[TransformInterface::FIELDS_TRANSFORM][0][SortByTransform::FIELDS_KEY];
                $dscFields = $transform[TransformInterface::FIELDS_TRANSFORM][1][SortByTransform::FIELDS_KEY];

                $ascDiff = array_diff($ascFields, $allFields);
                $dscDiff = array_diff($dscFields, $allFields);
                if (!empty($ascDiff)) {
                    $ascFields = $this->removeItemsFromArray($ascFields, $ascDiff);
                }

                if (!empty($dscDiff)) {
                    $dscFields = $this->removeItemsFromArray($dscFields, $dscDiff);
                }

                if (empty($ascFields) && empty($dscFields)) {
                    return null;
                }

                $transform[TransformInterface::FIELDS_TRANSFORM][0][SortByTransform::FIELDS_KEY] = $ascFields;
                $transform[TransformInterface::FIELDS_TRANSFORM][1][SortByTransform::FIELDS_KEY] = $dscFields;
                return $transform;

            case TransformInterface::COMPARISON_PERCENT_TRANSFORM;
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach($fields as $i=>$field) {
                    if (!in_array($field[ComparisonPercentTransform::NUMERATOR_KEY], $allFields) || !in_array($field[ComparisonPercentTransform::DENOMINATOR_KEY], $allFields)) {
                        unset($fields[$i]);
                    }
                }
                if (empty($fields)) {
                    return null;
                }

                $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;
                return $transform;

            case TransformInterface::REPLACE_TEXT_TRANSFORM;
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach($fields as $i=>$field) {
                    if (!in_array($field[ReplaceTextTransform::FIELD_KEY], $allFields)) {
                        unset($fields[$i]);
                    }
                }

                if (empty($fields)) {
                    return null;
                }

                $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;
                return $transform;

            case TransformInterface::ADD_CALCULATED_FIELD_TRANSFORM:
                $fields = $transform[TransformInterface::FIELDS_TRANSFORM];
                foreach($fields as $i=>$field) {
                    if ($this->checkIfAddCalculatedFieldTransformIsBroken($field, $allFields) === true) {
                        unset($fields[$i]);
                        continue;
                    }

                    $fields[$i] = $this->refreshAddCalculatedFieldTransformDefaultValue($field, $allFields);
                }

                if (empty($fields)) {
                    return null;
                }

                $transform[TransformInterface::FIELDS_TRANSFORM] = $fields;
                return $transform;

            default:
                return $transform;
        }
    }

    /**
     * @param array $transform
     * @param array $allFields
     * @return bool
     */
    protected function checkIfAddCalculatedFieldTransformIsBroken(array $transform, array $allFields)
    {
        $expression = $transform[AddCalculatedFieldTransform::EXPRESSION_CALCULATED_FIELD];
        preg_match_all('/\[([^\]]*)\]/', $expression, $matches);

        if (!isset($matches[1])) {
            return false;
        }

        return !empty(array_diff($matches[1], $allFields));
    }

    /**
     * @param array $transform
     * @param array $allFields
     * @return array
     */
    protected function refreshAddCalculatedFieldTransformDefaultValue(array $transform, array $allFields)
    {
        if (!isset($transform[AddCalculatedFieldTransform::DEFAULT_VALUE_CALCULATED_FIELD])) {
            return $transform;
        }

        $defaultValues = $transform[AddCalculatedFieldTransform::DEFAULT_VALUE_CALCULATED_FIELD];
        if (empty($defaultValues)) {
            return $transform;
        }

        foreach($defaultValues as $i=>$defaultValue) {
            if (!in_array($defaultValue[AddCalculatedFieldTransform::CONDITION_FIELD_KEY], $allFields)) {
                unset($defaultValues[$i]);
            }
        }

        $transform[AddCalculatedFieldTransform::DEFAULT_VALUE_CALCULATED_FIELD] = $defaultValues;

        return $transform;
    }
}