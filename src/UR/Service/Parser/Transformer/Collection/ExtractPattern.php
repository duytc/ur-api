<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;
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
        if ($isCaseInsensitive) {
            $this->pattern .= self::CASE_INSENSITIVE;
        }

        if ($isMultiLine) {
            $this->pattern .= self::MULTI_LINE;
        }

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

        if (!$this->isOverride) {
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
        $matched = preg_match($this->pattern, $str, $matches);
        if ($matched < 1) {
            return null;
        }

        $str = $matches[self::FIRST_MATCH];
        // convert replacement value if it has back references form
        $this->replacementValue = preg_replace_callback('(\[[0-9]\])', function ($matches) {
            return preg_replace('/\[([0-9])\]/', '$$1', $matches[0]);
        }, $this->replacementValue);

        try {
            $result = preg_replace($this->pattern, $this->replacementValue, $str);
            return $result;
        } catch (Exception $exception) {
            return null;
        }
    }

    /**
     * @inheritdoc
     */
    public function getDefaultPriority()
    {
        return self::TRANSFORM_PRIORITY_EXTRACT_PATTERN;
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
}