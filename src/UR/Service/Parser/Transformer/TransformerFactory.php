<?php

namespace UR\Service\Parser\Transformer;

use UR\Service\Parser\Transformer\Collection\AddCalculatedField;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Parser\Transformer\Collection\Augmentation;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Collection\ComparisonPercent;
use UR\Service\Parser\Transformer\Collection\ConvertCase;
use UR\Service\Parser\Transformer\Collection\ExtractPattern;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\NormalizeText;
use UR\Service\Parser\Transformer\Collection\ReplaceText;
use UR\Service\Parser\Transformer\Collection\SortByColumns;
use UR\Service\Parser\Transformer\Collection\SubsetGroup;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Column\NumberFormat;

class TransformerFactory
{
    private $transformTypes = [
        ColumnTransformerInterface::DATE_FORMAT,
        ColumnTransformerInterface::NUMBER_FORMAT,
        CollectionTransformerInterface::GROUP_BY,
        CollectionTransformerInterface::SORT_BY,
        CollectionTransformerInterface::ADD_FIELD,
        CollectionTransformerInterface::ADD_CALCULATED_FIELD,
        CollectionTransformerInterface::ADD_CONCATENATED_FIELD,
        CollectionTransformerInterface::COMPARISON_PERCENT,
        CollectionTransformerInterface::ADD_CONCATENATED_FIELD,
        CollectionTransformerInterface::REPLACE_TEXT,
        CollectionTransformerInterface::EXTRACT_PATTERN,
        CollectionTransformerInterface::AUGMENTATION,
        CollectionTransformerInterface::SUBSET_GROUP
    ];

    /**
     * @param $jsonTransform |null
     * @return array|TransformerInterface[]|TransformerInterface
     * @throws \Exception
     */
    public function getTransform(array $jsonTransform)
    {
        $this->validateCommonTransform($jsonTransform);
        /*
         * return type of single transform base on TYPE KEY
         */
        $transformObject = null;
        switch ($jsonTransform[ColumnTransformerInterface::TYPE_KEY]) {
            case ColumnTransformerInterface::NUMBER_FORMAT:
                $transformObject = $this->getNumberFormatTransform($jsonTransform);
                break;

            case ColumnTransformerInterface::DATE_FORMAT:
                $transformObject = $this->getDateFormatTransform($jsonTransform);
                break;

            case CollectionTransformerInterface::AUGMENTATION:
                $transformObject = $this->getAugmentationTransforms($jsonTransform);
                break;
            case CollectionTransformerInterface::SUBSET_GROUP:
                $transformObject = $this->getSubsetGroupTransforms($jsonTransform);
                break;
            default:
                $transformObject = $this->getCollectionTransforms($jsonTransform);
        }

        return $transformObject;
    }

    /**
     * get NumberFormat Transform
     *
     * @param array $jsonTransform
     * @return NumberFormat
     * @throws \Exception
     */
    private function getNumberFormatTransform(array $jsonTransform)
    {
        if (!array_key_exists(NumberFormat::DECIMALS, $jsonTransform)
            || !array_key_exists(NumberFormat::THOUSANDS_SEPARATOR, $jsonTransform)
        ) {
            throw new \Exception (sprintf('Either parameters: "%s", "%s" or "%s" does not exits in "Number format" transform',
                NumberFormat::FIELD_KEY,
                NumberFormat::DECIMALS,
                NumberFormat::THOUSANDS_SEPARATOR));
        }

        return new NumberFormat(
            $jsonTransform[NumberFormat::FIELD_KEY],
            $jsonTransform[NumberFormat::DECIMALS],
            $jsonTransform[NumberFormat::THOUSANDS_SEPARATOR]
        );
    }

    /**
     * get DateFormat Transform
     *
     * @param array $jsonTransform
     * @return DateFormat
     * @throws \Exception
     */
    private function getDateFormatTransform(array $jsonTransform)
    {
        if (!array_key_exists(DateFormat::FROM_KEY, $jsonTransform)
            || !array_key_exists(DateFormat::TO_KEY, $jsonTransform)
        ) {
            throw new \Exception (sprintf('Either parameters: "%s", "%s" or "%s" does not exits in "Date format" transform',
                DateFormat::FIELD_KEY,
                DateFormat::FROM_KEY,
                DateFormat::TO_KEY));
        }

        $timezone = array_key_exists(DateFormat::TIMEZONE_KEY, $jsonTransform) ? $jsonTransform[DateFormat::TIMEZONE_KEY] : DateFormat::DEFAULT_TIMEZONE;
        return new DateFormat(
            $jsonTransform[DateFormat::FIELD_KEY],
            $jsonTransform[DateFormat::FROM_KEY],
            $timezone,
            $jsonTransform[DateFormat::TO_KEY]
        );
    }

    /**
     * @param $jsonTransform
     * @return array|TransformerInterface[]|TransformerInterface
     * @throws \Exception
     */
    private function getCollectionTransforms(array $jsonTransform)
    {
        if (!is_array($jsonTransform) && $jsonTransform !== null) {
            throw new \Exception (sprintf('transform config must be an array'));
        }

        if (!array_key_exists(CollectionTransformerInterface::FIELDS_KEY, $jsonTransform)
        ) {
            throw new \Exception (sprintf('parameter "%s" does not exits in transform',
                CollectionTransformerInterface::FIELDS_KEY));
        }

        /*
         * return type of collection transform base on TYPE KEY
         * in case group or sort by: return object
         * other: return array of an Object
         *
        */
        $transformObject = null;
        $config = $jsonTransform[CollectionTransformerInterface::FIELDS_KEY];

        if ($config === null) {
            $config = [];
        }

        switch ($jsonTransform[CollectionTransformerInterface::TYPE_KEY]) {
            case CollectionTransformerInterface::GROUP_BY:
                $timezone = array_key_exists(GroupByColumns::TIMEZONE_KEY, $jsonTransform) ? $jsonTransform[GroupByColumns::TIMEZONE_KEY] : GroupByColumns::DEFAULT_TIMEZONE;
                $transformObject = $this->getGroupByTransform($config, $timezone);
                break;

            case CollectionTransformerInterface::SORT_BY:
                $transformObject = $this->getSortByTransform($config);
                break;

            case CollectionTransformerInterface::ADD_FIELD:
                $transformObject = $this->getAddFieldTransform($config);
                break;

            case CollectionTransformerInterface::ADD_CALCULATED_FIELD:
                $transformObject = $this->getAddCalculatedFieldTransforms($config);
                break;

            case CollectionTransformerInterface::COMPARISON_PERCENT:
                $transformObject = $this->getComparisonPercentTransforms($config);
                break;

            case CollectionTransformerInterface::REPLACE_TEXT:
                $transformObject = $this->getReplaceTextTransforms($config);
                break;

            case CollectionTransformerInterface::EXTRACT_PATTERN:
                $transformObject = $this->getExtractPatternTransforms($config);
                break;

            case CollectionTransformerInterface::CONVERT_CASE:
                $transformObject = $this->getConvertCaseTransforms($config);
                break;

            case CollectionTransformerInterface::NORMALIZE_TEXT:
                $transformObject = $this->getNormalizeTextTransforms($config);
                break;

            default:
                throw new \Exception (sprintf('Filter type must be one of "%s", "%s" given',
                    implode(", ", $this->transformTypes),
                    $jsonTransform[CollectionTransformerInterface::TYPE_KEY]));
        }

        return $transformObject;
    }

    /**
     * @param array $config
     * @param $timezone
     * @return GroupByColumns
     */
    private function getGroupByTransform(array $config, $timezone)
    {
        if (!is_array($config)) {
            return null;
        }

        return new GroupByColumns($config, $timezone);
    }

    /**
     * @param array $config
     * @return SortByColumns
     */
    private function getSortByTransform(array $config)
    {
        if (!is_array($config)) {
            return null;
        }

        $ascendingFields = [];
        $descendingFields = [];
        foreach ($config as $item) {
            if (!is_array($item)) {
                return null;
            }

            if (!array_key_exists(SortByColumns::NAMES, $item)
                || !array_key_exists(SortByColumns::DIRECTION, $item)
            ) {
                return null;
            }

            if ($item[SortByColumns::DIRECTION] === SortByColumns::ASC) {
                $ascendingFields = $item[SortByColumns::NAMES];
            }

            if ($item[SortByColumns::DIRECTION] === SortByColumns::DESC) {
                $descendingFields = $item[SortByColumns::NAMES];
            }
        }

        return new SortByColumns($ascendingFields, $descendingFields);
    }

    /**
     * @param array $addFieldConfigs
     * @return array
     */
    private function getAddFieldTransform(array $addFieldConfigs)
    {
        $addFieldTransforms = [];
        foreach ($addFieldConfigs as $addFieldConfig) {
            if (!is_array($addFieldConfig)
                || !array_key_exists(AddField::FIELD_KEY, $addFieldConfig)
                || !array_key_exists(AddField::VALUE_KEY, $addFieldConfig)
            ) {
                continue;
            }

            $addFieldTransforms[] = new AddField(
                $addFieldConfig[AddField::FIELD_KEY],
                $addFieldConfig[AddField::VALUE_KEY],
                null
            );
        }

        return $addFieldTransforms;
    }

    /**
     * @param $addCalculatedFieldConfigs
     * @return array
     */
    private function getAddCalculatedFieldTransforms(array $addCalculatedFieldConfigs)
    {
        $addCalculatedFieldTransforms = [];
        foreach ($addCalculatedFieldConfigs as $addCalculatedFieldConfig) {
            if (!is_array($addCalculatedFieldConfig)
                || !array_key_exists(AddCalculatedField::FIELD_KEY, $addCalculatedFieldConfig)
                || !array_key_exists(AddCalculatedField::EXPRESSION_KEY, $addCalculatedFieldConfig)
            ) {
                continue;
            }

            $addCalculatedFieldTransforms[] = new AddCalculatedField(
                $addCalculatedFieldConfig[AddCalculatedField::FIELD_KEY],
                $addCalculatedFieldConfig[AddCalculatedField::EXPRESSION_KEY],
                array_key_exists(AddCalculatedField::DEFAULT_VALUES_KEY, $addCalculatedFieldConfig) ? $addCalculatedFieldConfig[AddCalculatedField::DEFAULT_VALUES_KEY] : []
            );
        }

        return $addCalculatedFieldTransforms;
    }

    /**
     * @param $comparisonPercentConfigs
     * @return array
     */
    private function getComparisonPercentTransforms(array $comparisonPercentConfigs)
    {
        $comparisonPercentTransforms = [];
        foreach ($comparisonPercentConfigs as $comparisonPercentConfig) {
            if (!is_array($comparisonPercentConfig)
                || !array_key_exists(ComparisonPercent::FIELD_KEY, $comparisonPercentConfig)
                || !array_key_exists(ComparisonPercent::DENOMINATOR_KEY, $comparisonPercentConfig)
                || !array_key_exists(ComparisonPercent::NUMERATOR_KEY, $comparisonPercentConfig)
            ) {
                continue;
            }

            $comparisonPercentTransforms[] = new ComparisonPercent(
                $comparisonPercentConfig[ComparisonPercent::FIELD_KEY],
                $comparisonPercentConfig[ComparisonPercent::NUMERATOR_KEY],
                $comparisonPercentConfig[ComparisonPercent::DENOMINATOR_KEY]
            );
        }

        return $comparisonPercentTransforms;

    }

    /**
     * @param $replaceTextConfigs
     * @return array
     */
    private function getReplaceTextTransforms(array $replaceTextConfigs)
    {
        $replaceTextTransforms = [];
        foreach ($replaceTextConfigs as $replaceTextConfig) {
            if (!is_array($replaceTextConfig)
                || !array_key_exists(ReplaceText::FIELD_KEY, $replaceTextConfig)
                || !array_key_exists(ReplaceText::SEARCH_FOR_KEY, $replaceTextConfig)
                || !array_key_exists(ReplaceText::POSITION_KEY, $replaceTextConfig)
                || !array_key_exists(ReplaceText::REPLACE_WITH_KEY, $replaceTextConfig)
            ) {
                continue;
            }

            $replaceTextTransforms[] = new ReplaceText(
                $replaceTextConfig[ReplaceText::FIELD_KEY],
                $replaceTextConfig[ReplaceText::SEARCH_FOR_KEY],
                $replaceTextConfig[ReplaceText::POSITION_KEY],
                $replaceTextConfig[ReplaceText::REPLACE_WITH_KEY],
                !array_key_exists(ReplaceText::TARGET_FIELD_KEY, $replaceTextConfig) ? null : $replaceTextConfig[ReplaceText::TARGET_FIELD_KEY],
                !array_key_exists(ReplaceText::IS_OVERRIDE_KEY, $replaceTextConfig) ? false : $replaceTextConfig[ReplaceText::IS_OVERRIDE_KEY]
            );
        }

        return $replaceTextTransforms;
    }

    /**
     * @param $extractPatternConfigs
     * @return array
     */
    private function getExtractPatternTransforms(array $extractPatternConfigs)
    {
        $extractPatternTransforms = [];
        foreach ($extractPatternConfigs as $extractPatternConfig) {
            if (!is_array($extractPatternConfig)
                || !array_key_exists(ExtractPattern::FIELD_KEY, $extractPatternConfig)
                || !array_key_exists(ExtractPattern::REG_EXPRESSION_KEY, $extractPatternConfig)
            ) {
                continue;
            }

            $regexExpression = trim($extractPatternConfig[ExtractPattern::REG_EXPRESSION_KEY]);

            $extractPatternTransforms[] = new ExtractPattern(
                $extractPatternConfig[ExtractPattern::FIELD_KEY],
                $regexExpression,
                !array_key_exists(ExtractPattern::TARGET_FIELD_KEY, $extractPatternConfig) ? null : $extractPatternConfig[ExtractPattern::TARGET_FIELD_KEY],
                !array_key_exists(ExtractPattern::IS_OVERRIDE_KEY, $extractPatternConfig) ? false : $extractPatternConfig[ExtractPattern::IS_OVERRIDE_KEY],
                !array_key_exists(ExtractPattern::IS_REG_EXPRESSION_CASE_INSENSITIVE_KEY, $extractPatternConfig) ? false : $extractPatternConfig[ExtractPattern::IS_REG_EXPRESSION_CASE_INSENSITIVE_KEY],
                !array_key_exists(ExtractPattern::IS_REG_EXPRESSION_MULTI_LINE_KEY, $extractPatternConfig) ? false : $extractPatternConfig[ExtractPattern::IS_REG_EXPRESSION_MULTI_LINE_KEY],
                !array_key_exists(ExtractPattern::REPLACEMENT_VALUE_KEY, $extractPatternConfig) ? 1 : $extractPatternConfig[ExtractPattern::REPLACEMENT_VALUE_KEY]
            );
        }

        return $extractPatternTransforms;
    }

    /**
     * @param $convertCaseConfigs
     * @return array
     */
    private function getConvertCaseTransforms(array $convertCaseConfigs)
    {
        $extractPatternTransforms = [];
        foreach ($convertCaseConfigs as $convertCaseConfig) {
            if (!is_array($convertCaseConfig)
                || !array_key_exists(ConvertCase::FIELD_KEY, $convertCaseConfig)
                || !array_key_exists(ConvertCase::CONVERT_TYPE_KEY, $convertCaseConfig)
            ) {
                continue;
            }

            $isOverride = array_key_exists(ConvertCase::IS_OVERRIDE_KEY, $convertCaseConfig) ? filter_var($convertCaseConfig[ConvertCase::IS_OVERRIDE_KEY], FILTER_VALIDATE_BOOLEAN) : true;
            if ($isOverride === false && !array_key_exists(ConvertCase::TARGET_FIELD_KEY, $convertCaseConfig)) {
                continue;
            }

            $convertType = $convertCaseConfig[ConvertCase::CONVERT_TYPE_KEY];
            if (!in_array($convertType, [ConvertCase::LOWER_CASE_CONVERT, ConvertCase::UPPER_CASE_CONVERT])) {
                continue;
            }

            $extractPatternTransforms[] = new ConvertCase(
                $convertCaseConfig[ConvertCase::FIELD_KEY],
                $convertType,
                $isOverride,
                $convertCaseConfig[ConvertCase::TARGET_FIELD_KEY]
            );
        }

        return $extractPatternTransforms;
    }

    /**
     * @param $normalizeTextConfigs
     * @return array
     */
    private function getNormalizeTextTransforms(array $normalizeTextConfigs)
    {
        $extractPatternTransforms = [];
        foreach ($normalizeTextConfigs as $normalizeTextConfig) {
            if (!is_array($normalizeTextConfig)
                || !array_key_exists(NormalizeText::FIELD_KEY, $normalizeTextConfig)
            ) {
                continue;
            }

            $isOverride = array_key_exists(NormalizeText::IS_OVERRIDE_KEY, $normalizeTextConfig) ? filter_var($normalizeTextConfig[NormalizeText::IS_OVERRIDE_KEY], FILTER_VALIDATE_BOOLEAN) : true;
            if ($isOverride === false && !array_key_exists(NormalizeText::TARGET_FIELD_KEY, $normalizeTextConfig)) {
                continue;
            }

            $extractPatternTransforms[] = new NormalizeText(
                $normalizeTextConfig[NormalizeText::FIELD_KEY],
                $isOverride,
                $normalizeTextConfig[NormalizeText::TARGET_FIELD_KEY],
                array_key_exists(NormalizeText::NUMBER_REMOVED_KEY, $normalizeTextConfig) ? filter_var($normalizeTextConfig[NormalizeText::NUMBER_REMOVED_KEY], FILTER_VALIDATE_BOOLEAN) : false,
                array_key_exists(NormalizeText::NON_ALPHA_NUMERIC_REMOVED_KEY, $normalizeTextConfig) ? filter_var($normalizeTextConfig[NormalizeText::NON_ALPHA_NUMERIC_REMOVED_KEY], FILTER_VALIDATE_BOOLEAN) : false,
                array_key_exists(NormalizeText::WHITE_SPACE_REMOVED_KEY, $normalizeTextConfig) ? filter_var($normalizeTextConfig[NormalizeText::WHITE_SPACE_REMOVED_KEY], FILTER_VALIDATE_BOOLEAN) : false
            );
        }

        return $extractPatternTransforms;
    }

    /**
     * @param $augmentationConfig
     * @return array
     */
    private function getAugmentationTransforms(array $augmentationConfig)
    {
        $augmentationTransforms = [];
        if (!is_array($augmentationConfig)
            || !array_key_exists(Augmentation::MAP_DATA_SET, $augmentationConfig)
            || !array_key_exists(Augmentation::MAP_CONDITION_KEY, $augmentationConfig)
            || !array_key_exists(Augmentation::MAP_FIELDS_KEY, $augmentationConfig)
        ) {
            return [];
        }

        if (!is_array($augmentationConfig[Augmentation::MAP_CONDITION_KEY])
            || !is_array($augmentationConfig[Augmentation::MAP_FIELDS_KEY])
        ) {
            return [];
        }

        foreach ($augmentationConfig[Augmentation::MAP_CONDITION_KEY] as $mapCondition) {
            if (!array_key_exists(Augmentation::MAP_DATA_SET_SIDE, $mapCondition)
                || !array_key_exists(Augmentation::DATA_SOURCE_SIDE, $mapCondition)
            ) {
                return [];
            }
        }

        foreach ($augmentationConfig[Augmentation::MAP_FIELDS_KEY] as $mapField) {
            if (!is_array($mapField)) {
                return [];
            }

            if (!array_key_exists(Augmentation::MAP_DATA_SET_SIDE, $mapField)
                || !array_key_exists(Augmentation::DATA_SOURCE_SIDE, $mapField)
            ) {
                return [];
            }
        }

        $augmentationTransforms[] = new Augmentation(
            $augmentationConfig[Augmentation::MAP_DATA_SET],
            $augmentationConfig[Augmentation::MAP_CONDITION_KEY],
            $augmentationConfig[Augmentation::MAP_FIELDS_KEY],
            array_key_exists(Augmentation::DROP_UNMATCHED, $augmentationConfig) ? $augmentationConfig[Augmentation::DROP_UNMATCHED] : false,
            !array_key_exists(Augmentation::CUSTOM_CONDITION, $augmentationConfig) ? null : $augmentationConfig[Augmentation::CUSTOM_CONDITION]
        );

        return $augmentationTransforms;
    }

    private function getSubsetGroupTransforms(array $subsetGroupConfig)
    {
        $subsetTransforms = [];
        if (!is_array($subsetGroupConfig)
            || !array_key_exists(SubsetGroup::MAP_FIELDS_KEY, $subsetGroupConfig)
            || !array_key_exists(SubsetGroup::GROUP_FIELD_KEY, $subsetGroupConfig)
        ) {
            return [];
        }

        if (!is_array($subsetGroupConfig[SubsetGroup::MAP_FIELDS_KEY])
            || !is_array($subsetGroupConfig[SubsetGroup::GROUP_FIELD_KEY])
        ) {
            return [];
        }


        foreach ($subsetGroupConfig[SubsetGroup::MAP_FIELDS_KEY] as $mapField) {
            if (!is_array($mapField)) {
                return [];
            }

            if (!array_key_exists(SubsetGroup::GROUP_DATA_SET_SIDE, $mapField)
                || !array_key_exists(SubsetGroup::DATA_SOURCE_SIDE, $mapField)
            ) {
                return [];
            }
        }

        $subsetTransforms[] = new SubsetGroup(
            $subsetGroupConfig[SubsetGroup::GROUP_FIELD_KEY],
            $subsetGroupConfig[SubsetGroup::MAP_FIELDS_KEY]
        );

        return $subsetTransforms;
    }

    public function getAugmentationTransform(array $augmentationTransform)
    {
        $this->validateCommonTransform($augmentationTransform);

        if ($augmentationTransform[ColumnTransformerInterface::TYPE_KEY] !== CollectionTransformerInterface::AUGMENTATION) {
            return [];
        }

        return $this->getAugmentationTransforms($augmentationTransform);
    }


    private function validateCommonTransform($jsonTransform)
    {
        if (!is_array($jsonTransform) && $jsonTransform !== null) {
            throw new \Exception (sprintf('transform config must be an array'));
        }

        if (!array_key_exists(ColumnTransformerInterface::TYPE_KEY, $jsonTransform)
        ) {
            throw new \Exception (sprintf('parameter "%s" does not exits in transform',
                ColumnTransformerInterface::TYPE_KEY));
        }
    }
}