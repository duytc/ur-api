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
        $priority = 0;
        foreach ($configs as $config) {
            $priority++;
            if (!array_key_exists(TransformType::FIELDS, $config)
                || !array_key_exists(TransformType::TYPE, $config)
            ) {
                continue;
            }

            $fields = $config[TransformType::FIELDS];
            $this->type = $config[TransformType::TYPE];
            switch ($this->type) {
                case TransformType::GROUP_BY:
                    $this->setGroupByTransforms($fields, $priority);
                    break;

                case TransformType::SORT_BY:
                    $this->setSortByTransforms($fields, $priority);
                    break;

                case TransformType::ADD_FIELD:
                    $this->setAddFieldTransform($fields, $priority);
                    break;

                case TransformType::ADD_CALCULATED_FIELD:
                    $this->setAddCalculatedFieldTransforms($fields, $priority);
                    break;

                case TransformType::COMPARISON_PERCENT:
                    $this->setComparisonPercentTransforms($fields, $priority);
                    break;

                case TransformType::REPLACE_TEXT:
                    $this->setReplaceTextTransforms($fields, $priority);
                    break;

                case TransformType::EXTRACT_PATTERN:
                    $this->setExtractPatternTransforms($fields, $priority);
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
     * @param $priority
     */
    public function setAddFieldTransform($addFieldConfigs, $priority)
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
                null,
                $priority
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
     * @param $priority
     */
    public function setExtractPatternTransforms($extractPatternTransforms, $priority)
    {
        foreach ($extractPatternTransforms as $extractPatternTransform) {
            if (!is_array($extractPatternTransform)
                || !array_key_exists(TransformType::FIELD, $extractPatternTransform)
                || !array_key_exists(TransformType::REG_EXPRESSION, $extractPatternTransform)
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
                !array_key_exists(TransformType::TARGET_FIELD, $extractPatternTransform) ? null : $extractPatternTransform[TransformType::TARGET_FIELD],
                !array_key_exists(TransformType::IS_OVERRIDE, $extractPatternTransform) ? false : $extractPatternTransform[TransformType::IS_OVERRIDE],
                !array_key_exists(TransformType::IS_REG_EXPRESSION_CASE_INSENSITIVE, $extractPatternTransform) ? false : $extractPatternTransform[TransformType::IS_REG_EXPRESSION_CASE_INSENSITIVE],
                !array_key_exists(TransformType::IS_REG_EXPRESSION_MULTI_LINE, $extractPatternTransform) ? false : $extractPatternTransform[TransformType::IS_REG_EXPRESSION_MULTI_LINE],
                !array_key_exists(TransformType::REPLACEMENT_VALUE, $extractPatternTransform) ? 1 : $extractPatternTransform[TransformType::REPLACEMENT_VALUE],
                $priority
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
     * @param array $groupByTransforms
     * @param $priority
     */
    public function setGroupByTransforms(array $groupByTransforms, $priority)
    {
        $this->groupByTransforms = new GroupByColumns($groupByTransforms, $priority);
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
     * @param $priority
     */
    public function setSortByTransforms(array $sortByTransforms, $priority)
    {
        $xxx[] = $sortByTransforms;
        $this->sortByTransforms = new SortByColumns($xxx, $priority);
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
     * @param $priority
     */
    public function setAddCalculatedFieldTransforms(array $addCalculatedFieldTransforms, $priority)
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
                $addCalculatedFieldTransform[TransformType::EXPRESSION],
                0,
                $priority
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
     * @param $priority
     */
    public function setComparisonPercentTransforms(array $comparisonPercentTransforms, $priority)
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
                $comparisonPercentTransform[TransformType::DENOMINATOR],
                $priority
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
     * @param $priority
     */
    public function setReplaceTextTransforms(array $replaceTextTransforms, $priority)
    {
        foreach ($replaceTextTransforms as $replaceTextTransform) {
            if (!is_array($replaceTextTransform)
                || !array_key_exists(TransformType::FIELD, $replaceTextTransform)
                || !array_key_exists(TransformType::SEARCH_FOR, $replaceTextTransform)
                || !array_key_exists(TransformType::POSITION, $replaceTextTransform)
                || !array_key_exists(TransformType::REPLACE_WITH, $replaceTextTransform)
            ) {
                return;
            }

            $this->replaceTextTransforms[] = new ReplaceText(
                $replaceTextTransform[TransformType::FIELD],
                $replaceTextTransform[TransformType::SEARCH_FOR],
                $replaceTextTransform[TransformType::POSITION],
                $replaceTextTransform[TransformType::REPLACE_WITH],
                !array_key_exists(TransformType::TARGET_FIELD, $replaceTextTransform) ? null : $replaceTextTransform[TransformType::TARGET_FIELD],
                !array_key_exists(TransformType::IS_OVERRIDE, $replaceTextTransform) ? false : $replaceTextTransform[TransformType::IS_OVERRIDE],
                $priority
            );
        }
    }
}