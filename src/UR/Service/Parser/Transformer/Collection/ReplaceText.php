<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;

class ReplaceText implements CollectionTransformerInterface, CollectionTransformerJsonConfigInterface
{
    const SEARCH_FOR_KEY = 'searchFor';
    const POSITION_KEY = 'position';
    const REPLACE_WITH_KEY = 'replaceWith';
    const TARGET_FIELD_KEY = 'targetField';
    const IS_OVERRIDE_KEY = 'isOverride';
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

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = [], $mapFields = [])
    {
        $rows = $collection->getRows();
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

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

        return new Collection($columns, $rows, $types);
    }

    public function replaceText(array &$row, $field)
    {
        $stringReplaced = $row[$field];

        if ($stringReplaced === null) {
            return null;
        }

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
     * @return string
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @param string $position
     */
    public function setPosition(string $position)
    {
        $this->position = $position;
    }

    /**
     * @return string
     */
    public function getSearchFor()
    {
        return $this->searchFor;
    }

    /**
     * @param string $searchFor
     */
    public function setSearchFor(string $searchFor)
    {
        $this->searchFor = $searchFor;
    }

    /**
     * @return string
     */
    public function getReplaceWith()
    {
        return $this->replaceWith;
    }

    /**
     * @param string $replaceWith
     */
    public function setReplaceWith(string $replaceWith)
    {
        $this->replaceWith = $replaceWith;
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

    public function getJsonTransformFieldsConfig()
    {
        $transformFields = [];
        $transformFields[self::FIELD_KEY] = $this->field;
        $transformFields[self::IS_OVERRIDE_KEY] = $this->isOverride;
        $transformFields[self::TARGET_FIELD_KEY] = $this->targetField;
        $transformFields[self::POSITION_KEY] = $this->position;
        $transformFields[self::REPLACE_WITH_KEY] = $this->replaceWith;
        $transformFields[self::SEARCH_FOR_KEY] = $this->searchFor;
        return $transformFields;
    }
}