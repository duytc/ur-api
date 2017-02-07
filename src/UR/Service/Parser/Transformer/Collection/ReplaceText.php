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

    protected $field;

    public function __construct($field, $searchFor, $position = self::POSITION_AT_BEGINNING, $replaceWith)
    {
        $this->field = $field;
        $this->searchFor = $searchFor;
        $this->position = $position;
        $this->replaceWith = $replaceWith;
    }

    public function transform(Collection $collection)
    {
        $rows = $collection->getRows();
        if (count($rows) < 1) {
            return $collection;
        }

        foreach ($rows as &$row) {
            $this->replaceText($row);
        }

        $collection->setRows($rows);

        return $collection;
    }

    public function replaceText(array &$row)
    {
        switch ($this->position) {
            case self::POSITION_ANY_WHERE:
                $row[$this->field] = str_replace($this->searchFor, $this->replaceWith, $row[$this->field]);
                break;
            case self::POSITION_AT_BEGINNING:
                if ($this->startsWith($row[$this->field], $this->searchFor)) {
                    $row[$this->field] = substr_replace($row[$this->field], $this->replaceWith, 0, strlen($this->searchFor));
                }
                break;
            case self::POSITION_AT_THE_END:
                if ($this->endsWith($row[$this->field], $this->searchFor)) {
                    $row[$this->field] = substr_replace($row[$this->field], $this->replaceWith, strlen($row[$this->field]) - strlen($this->searchFor), strlen($this->searchFor));
                }
                break;
        }

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
        return self::TRANSFORM_REPLACE_TEXT;
    }
}