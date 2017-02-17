<?php

namespace UR\Model\Core;


use UR\Service\DataSet\TransformType;
use UR\Service\Parser\Transformer\Collection\AddCalculatedField;
use UR\Service\Parser\Transformer\Collection\AddField;
use UR\Service\Parser\Transformer\Collection\ComparisonPercent;
use UR\Service\Parser\Transformer\Collection\GroupByColumns;
use UR\Service\Parser\Transformer\Collection\ExtractPattern;
use UR\Service\Parser\Transformer\Collection\ReplaceText;
use UR\Service\Parser\Transformer\Collection\SortByColumns;

class ConnectedDataSourceCollectionTransform
{
    const START_REGEX_SPECIAL = '/';
    const END_REGEX_CASE_INSENSITIVE = '/i';
    const END_REGEX_MULTI_LINE = '/i';

    protected $prevent_special_regex_strings = [
        self::END_REGEX_CASE_INSENSITIVE,
        self::END_REGEX_MULTI_LINE
    ];

    protected $field;

    /**@var AddField[] $addFieldTransforms */
    protected $addFieldTransforms = [];

    /**@var AddCalculatedField[] $addCalculatedFieldTransforms */
    protected $addCalculatedFieldTransforms = [];

    /**@var ExtractPattern[] $extractPatternTransforms */
    protected $extractPatternTransforms = [];

    /**@var ComparisonPercent[] $comparisonPercentTransforms */
    protected $comparisonPercentTransforms = [];

    /**@var ReplaceText[] $replaceTextTransforms */
    protected $replaceTextTransforms = [];

    /**@var GroupByColumns $groupByTransforms */
    protected $groupByTransforms;

    /**@var SortByColumns $sortByTransforms */
    protected $sortByTransforms;

    protected $configs;
    protected $type;

    /**
     * ConnectedDataSourceCollectionTransform constructor.
     * @param array $configs
     */
    public function __construct(array $configs)
    {
        foreach ($configs as $config) {
            if (!array_key_exists(TransformType::FIELDS, $config)
                || !array_key_exists(TransformType::TYPE, $config)
            ) {
                continue;
            }

            $fields = $config[TransformType::FIELDS];
            $this->type = $config[TransformType::TYPE];
            switch ($this->type) {
                case TransformType::GROUP_BY:
                    $this->setGroupByTransforms($fields);
                    break;

                case TransformType::SORT_BY:
                    $this->setSortByTransforms($fields);
                    break;

                case TransformType::ADD_FIELD:
                    $this->setAddFieldTransform($fields);
                    break;

                case TransformType::ADD_CALCULATED_FIELD:
                    $this->setAddCalculatedFieldTransforms($fields);
                    break;

                case TransformType::COMPARISON_PERCENT:
                    $this->setComparisonPercentTransforms($fields);
                    break;

                case TransformType::REPLACE_TEXT:
                    $this->setReplaceTextTransforms($fields);
                    break;

                case TransformType::EXTRACT_PATTERN:
                    $this->setExtractPatternTransforms($fields);
                    break;

                default:
                    break;
            }
        }
    }

    /**
     * @return mixed
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return AddField[]
     */
    public function getAddFieldTransform()
    {
        return $this->addFieldTransforms;
    }

    /**
     * @param array $addFieldConfigs
     */
    public function setAddFieldTransform($addFieldConfigs)
    {
        foreach ($addFieldConfigs as $addFieldConfig) {
            if (!is_array($addFieldConfig)
                || !array_key_exists(TransformType::FIELD, $addFieldConfig)
                || !array_key_exists(TransformType::VALUE, $addFieldConfig)
            ) {
                return;
            }

            $this->addFieldTransforms[] = new AddField(
                $addFieldConfig[TransformType::FIELD],
                $addFieldConfig[TransformType::VALUE],
                null
            );
        }
    }

    /**
     * @return ExtractPattern[]
     */
    public function getExtractPatternTransforms()
    {
        return $this->extractPatternTransforms;
    }

    /**
     * @param array $extractPatternTransforms
     */
    public function setExtractPatternTransforms($extractPatternTransforms)
    {
        foreach ($extractPatternTransforms as $extractPatternTransform) {
            if (!is_array($extractPatternTransform)
                || !array_key_exists(TransformType::FIELD, $extractPatternTransform)
                || !array_key_exists(TransformType::REG_EXPRESSION, $extractPatternTransform)
                || !array_key_exists(TransformType::TARGET_FIELD, $extractPatternTransform)
                || !array_key_exists(TransformType::IS_OVERRIDE, $extractPatternTransform)
                || !array_key_exists(TransformType::IS_REG_EXPRESSION_CASE_INSENSITIVE, $extractPatternTransform)
                || !array_key_exists(TransformType::IS_REG_EXPRESSION_MULTI_LINE, $extractPatternTransform)
            ) {
                return;
            }

            $regexExpression = trim($extractPatternTransform[TransformType::REG_EXPRESSION]);
            if ((strcmp(substr($regexExpression, 0, 1), self::START_REGEX_SPECIAL) === 0)
            ) {
                return;
            }

            $regexExpression = sprintf("/%s/", $regexExpression);
            $this->extractPatternTransforms[] = new ExtractPattern(
                $extractPatternTransform[TransformType::FIELD],
                $regexExpression,
                $extractPatternTransform[TransformType::TARGET_FIELD],
                $extractPatternTransform[TransformType::IS_OVERRIDE],
                $extractPatternTransform[TransformType::IS_REG_EXPRESSION_CASE_INSENSITIVE],
                $extractPatternTransform[TransformType::IS_REG_EXPRESSION_MULTI_LINE]
            );
        }
    }

    /**
     * @return GroupByColumns|null
     */
    public function getGroupByTransforms()
    {
        return $this->groupByTransforms;
    }

    /**
     * @param $groupByTransforms
     */
    public function setGroupByTransforms(array $groupByTransforms)
    {
        $this->groupByTransforms = new GroupByColumns($groupByTransforms);
    }

    /**
     * @return SortByColumns|null
     */
    public function getSortByTransforms()
    {
        return $this->sortByTransforms;
    }

    /**
     * @param array $sortByTransforms
     */
    public function setSortByTransforms(array $sortByTransforms)
    {
        $xxx[] = $sortByTransforms;
        $this->sortByTransforms = new SortByColumns($xxx);
    }

    /**
     * @return AddCalculatedField[]|null
     */
    public function getAddCalculatedFieldTransforms()
    {
        return $this->addCalculatedFieldTransforms;
    }

    /**
     * @param array $addCalculatedFieldTransforms
     */
    public function setAddCalculatedFieldTransforms(array $addCalculatedFieldTransforms)
    {
        foreach ($addCalculatedFieldTransforms as $addCalculatedFieldTransform) {
            if (!is_array($addCalculatedFieldTransform)
                || !array_key_exists(TransformType::FIELD, $addCalculatedFieldTransform)
                || !array_key_exists(TransformType::EXPRESSION, $addCalculatedFieldTransform)
            ) {
                return;
            }

            $this->addCalculatedFieldTransforms[] = new AddCalculatedField(
                $addCalculatedFieldTransform[TransformType::FIELD],
                $addCalculatedFieldTransform[TransformType::EXPRESSION]
            );
        }
    }

    /**
     * @return ComparisonPercent[]|null
     */
    public function getComparisonPercentTransforms()
    {
        return $this->comparisonPercentTransforms;
    }

    /**
     * @param array $comparisonPercentTransforms
     */
    public function setComparisonPercentTransforms(array $comparisonPercentTransforms)
    {
        foreach ($comparisonPercentTransforms as $comparisonPercentTransform) {
            if (!is_array($comparisonPercentTransform)
                || !array_key_exists(TransformType::FIELD, $comparisonPercentTransform)
                || !array_key_exists(TransformType::DENOMINATOR, $comparisonPercentTransform)
                || !array_key_exists(TransformType::NUMERATOR, $comparisonPercentTransform)
            ) {
                return;
            }

            $this->comparisonPercentTransforms[] = new ComparisonPercent(
                $comparisonPercentTransform[TransformType::FIELD],
                $comparisonPercentTransform[TransformType::NUMERATOR],
                $comparisonPercentTransform[TransformType::DENOMINATOR]
            );
        }
    }

    /**
     * @return ReplaceText[]|null
     */
    public function getReplaceTextTransforms()
    {
        return $this->replaceTextTransforms;
    }

    /**
     * @param array $replaceTextTransforms
     */
    public function setReplaceTextTransforms(array $replaceTextTransforms)
    {
        foreach ($replaceTextTransforms as $replaceTextTransform) {
            if (!is_array($replaceTextTransform)
                || !array_key_exists(TransformType::FIELD, $replaceTextTransform)
                || !array_key_exists(TransformType::SEARCH_FOR, $replaceTextTransform)
                || !array_key_exists(TransformType::POSITION, $replaceTextTransform)
                || !array_key_exists(TransformType::REPLACE_WITH, $replaceTextTransform)
                || !array_key_exists(TransformType::TARGET_FIELD, $replaceTextTransform)
                || !array_key_exists(TransformType::IS_OVERRIDE, $replaceTextTransform)
            ) {
                return;
            }

            $this->replaceTextTransforms[] = new ReplaceText(
                $replaceTextTransform[TransformType::FIELD],
                $replaceTextTransform[TransformType::SEARCH_FOR],
                $replaceTextTransform[TransformType::POSITION],
                $replaceTextTransform[TransformType::REPLACE_WITH],
                $replaceTextTransform[TransformType::TARGET_FIELD],
                $replaceTextTransform[TransformType::IS_OVERRIDE]
            );
        }
    }
}