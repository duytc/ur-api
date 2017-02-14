<?php

namespace UR\Service\Parser\Transformer\Collection;

use Symfony\Component\Config\Definition\Exception\Exception;
use UR\Service\DTO\Collection;

class ReplacePattern implements CollectionTransformerInterface
{
    const FILE_NAME_FIELD = '__filename';

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

    public function __construct($field, $pattern, $targetField, $isOverride = true)
    {
        $this->field = $field;
        $this->pattern = $pattern;
        $this->targetField = $targetField;
        $this->isOverride = $isOverride;
    }

    public function transform(Collection $collection)
    {
        $rows = $collection->getRows();
        $columns = $collection->getColumns();

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

        return new Collection($columns, $rows);
    }

    public function getRegexValue($str)
    {
        try {
            preg_match($this->pattern, $str, $matches);
            if ($matches === null) {
                return null;
            }

            if (count($matches) < 1) {
                return null;
            }

        } catch (Exception $exception) {
            return null;
        }

        return $matches[0];
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return self::TRANSFORM_REPLACE_PATTERN;
    }
}