<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use \Exception;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;

class ExtractPattern implements CollectionTransformerInterface
{
    const FIRST_MATCH = 0;
    const FILE_NAME_FIELD = '__filename';
    const CASE_INSENSITIVE = 'i';
    const MULTI_LINE = 'm';
    const START_REGEX_SPECIAL = '/';
    const REG_EXPRESSION_KEY = 'searchPattern';
    const TARGET_FIELD_KEY = 'targetField';
    const IS_OVERRIDE_KEY = 'isOverride';
    const IS_REG_EXPRESSION_CASE_INSENSITIVE_KEY = 'isCaseInsensitive';
    const IS_REG_EXPRESSION_MULTI_LINE_KEY = 'isMultiLine';
    const REPLACEMENT_VALUE_KEY = 'replacementValue';

    /**
     * @var string
     */
    protected $pattern;

    /**
     * @var string
     */
    protected $field;

    /**
     * @var string
     */
    protected $targetField;

    /**
     * @var boolean
     */
    protected $isOverride;

    /**
     * @var boolean
     */
    protected $isCaseInsensitive;

    /**
     * @var boolean
     */
    protected $isMultiLine;

    /**
     * @var string
     */
    protected $replacementValue;

    public function __construct($field, $pattern, $targetField, $isOverride = true, $isCaseInsensitive, $isMultiLine, $replacementValue)
    {
        $this->field = $field;
        $this->targetField = $targetField;
        $this->isOverride = $isOverride;
        $this->isCaseInsensitive = $isCaseInsensitive;
        $this->isMultiLine = $isMultiLine;
        $this->pattern = $pattern;


        $this->replacementValue = $replacementValue;
    }

    /**
     * @param Collection $collection
     * @param EntityManagerInterface|null $em
     * @param ConnectedDataSourceInterface|null $connectedDataSource
     * @return Collection
     */
    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null)
    {
        $rows = $collection->getRows();
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        if (!$this->isOverride && !in_array($this->targetField, $columns)) {
            $columns[] = $this->targetField;
        }

        if (count($rows) < 1) {
            return $collection;
        }

        foreach ($rows as &$row) {
            if (!array_key_exists($this->field, $row)) {
                return $collection;
            }

            if ($this->isOverride) {
                $row[$this->field] = $this->getRegexValue($row[$this->field]);
            } else {
                $row[$this->targetField] = $this->getRegexValue($row[$this->field]);
            }
        }

        return new Collection($columns, $rows, $types);
    }

    private function getRegexValue($str)
    {
        if ((substr($this->pattern, 0, 1) === self::START_REGEX_SPECIAL)
        ) {
            return null;
        }

        $regexExpression = $this->addPrefixAndSuffixToPatter();

        try {
            $matched = @preg_match($regexExpression, $str, $matches);
            if ($matched < 1) {
                return null;
            }

            $str = $matches[self::FIRST_MATCH];
            // convert replacement value if it has back references form
            $this->replacementValue = @preg_replace_callback('(\[[0-9]\])', function ($matches) {
                return preg_replace('/\[([0-9])\]/', '$$1', $matches[0]);
            }, $this->replacementValue);

            return preg_replace($regexExpression, $this->replacementValue, $str);
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * @param string $pattern
     */
    public function setPattern(string $pattern)
    {
        $this->pattern = $pattern;
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @param string $field
     */
    public function setField(string $field)
    {
        $this->field = $field;
    }

    /**
     * @return string
     */
    public function getTargetField()
    {
        return $this->targetField;
    }

    /**
     * @param string $targetField
     */
    public function setTargetField(string $targetField)
    {
        $this->targetField = $targetField;
    }

    /**
     * @return boolean
     */
    public function isIsOverride()
    {
        return $this->isOverride;
    }

    /**
     * @param boolean $isOverride
     */
    public function setIsOverride(bool $isOverride)
    {
        $this->isOverride = $isOverride;
    }

    public function validate()
    {
        // TODO: Implement validate() method.
    }

    private function addPrefixAndSuffixToPatter()
    {
        $regexExpression = sprintf("/%s/", $this->pattern);

        if ($this->isCaseInsensitive) {
            $regexExpression .= self::CASE_INSENSITIVE;
        }

        if ($this->isMultiLine) {
            $regexExpression .= self::MULTI_LINE;
        }

        return $regexExpression;
    }

    public function getJsonTransformFieldsConfig()
    {
        $transformFields = [];
        $transformFields[self::FIELD_KEY] = $this->field;
        $transformFields[self::IS_OVERRIDE_KEY] = $this->isOverride;
        $transformFields[self::TARGET_FIELD_KEY] = $this->targetField;
        $transformFields[self::REG_EXPRESSION_KEY] = $this->pattern;
        $transformFields[self::REPLACEMENT_VALUE_KEY] = $this->replacementValue;
        $transformFields[self::IS_REG_EXPRESSION_MULTI_LINE_KEY] = $this->isMultiLine;
        $transformFields[self::IS_REG_EXPRESSION_CASE_INSENSITIVE_KEY] = $this->isCaseInsensitive;
        return $transformFields;
    }
}