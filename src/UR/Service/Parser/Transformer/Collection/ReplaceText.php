<?php

namespace UR\Service\Parser\Transformer\Collection;

use UR\Service\DTO\Collection;

class ReplaceText implements CollectionTransformerInterface
{
    const POSITION_AT_BEGINNING = 'at the beginning';
    const POSITION_AT_THE_END = 'at the end';
    const POSITION_ANY_WHERE = 'anywhere';

    /**
     * @var string
     */
    protected $position;

    /**
     * @var string
     */
    protected $searchFor;

    /**
     * @var string
     */
    protected $replaceWith;

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

    public function __construct($field, $searchFor, $position = self::POSITION_AT_BEGINNING, $replaceWith, $targetField, $isOverride = true)
    {
        $this->field = $field;
        $this->searchFor = $searchFor;
        $this->position = $position;
        $this->replaceWith = $replaceWith;
        $this->targetField = $targetField;
        $this->isOverride = $isOverride;
    }

    public function transform(Collection $collection)
    {
        $rows = $collection->getRows();
        $columns = $collection->getColumns();

        $isMultiTrans = false;
        if (!$this->isOverride) {
            if (!in_array($this->targetField, $columns)) {
                $columns[] = $this->targetField;
            } else {
                $isMultiTrans = true;
            }
        }

        if (count($rows) < 1) {
            return $collection;
        }

        foreach ($rows as &$row) {
            if (!array_key_exists($this->field, $row)) {
                return $collection;
            }

            if ($this->isOverride) {
                $row[$this->field] = $this->replaceText($row, $this->field);
            } else {
                if ($isMultiTrans) {
                    $row[$this->targetField] = $this->replaceText($row, $this->targetField);
                } else {
                    $row[$this->targetField] = $this->replaceText($row, $this->field);
                }
            }
        }

        return new Collection($columns, $rows);
    }

    public function replaceText(array &$row, $field)
    {
        $stringReplaced = $row[$field];
        switch ($this->position) {
            case self::POSITION_ANY_WHERE:
                $stringReplaced = str_replace($this->searchFor, $this->replaceWith, $row[$field]);
                break;
            case self::POSITION_AT_BEGINNING:
                if ($this->startsWith($row[$field], $this->searchFor)) {
                    $stringReplaced = substr_replace($row[$field], $this->replaceWith, 0, strlen($this->searchFor));
                }
                break;
            case self::POSITION_AT_THE_END:
                if ($this->endsWith($row[$this->field], $this->searchFor)) {
                    $stringReplaced = substr_replace($row[$field], $this->replaceWith, strlen($row[$field]) - strlen($this->searchFor), strlen($this->searchFor));
                }
                break;
        }

        return $stringReplaced;
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    protected function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    protected function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0) {
            return true;
        }

        return (substr($haystack, -$length) === $needle);
    }

    /**
     * @inheritdoc
     */
    public function getPriority()
    {
        return self::TRANSFORM_PRIORITY_REPLACE_TEXT;
    }
}