<?php

namespace UR\Domain\DTO\Report\Transforms;

use Doctrine\Common\Proxy\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class ReplaceTextTransform extends AbstractTransform implements TransformInterface
{

    const PRIORITY = 3;
    const TRANSFORMS_TYPE = 'replaceText';

    const FIELD_KEY = 'field';
    const SEARCH_FOR_KEY = 'searchFor';
    const POSITION_KEY = 'position';
    const REPLACE_WITH_KEY = 'replaceWith';
    const IS_OVERRIDE_KEY = 'isOverride';
    const TARGET_FIELD_KEY = 'targetField';

    const ANYWHERE_KEY = 'anywhere';
    const AT_THE_BEGINNING_POSITION_KEY = 'at the beginning';
    const AT_THE_END_POSITION_KEY = 'at the end';

    protected $fieldName;
    protected $searchField;
    protected $position;
    protected $replaceWith;
    protected $isOverride;
    protected $targetField;

    /**
     * ReplaceTextTransform constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        parent::__construct();

        if (!array_key_exists(self::FIELD_KEY, $data) || !array_key_exists(self::SEARCH_FOR_KEY, $data)
            || !array_key_exists(self::POSITION_KEY, $data) || !array_key_exists(self::REPLACE_WITH_KEY, $data)
            || !array_key_exists(self::TARGET_FIELD_KEY, $data) || !array_key_exists(self::IS_OVERRIDE_KEY, $data)
        ) {
            throw new InvalidArgumentException('either "searchFor" or "position" or "replaceWith" or "isOverride" or "targetField" is missing');
        }

        $this->fieldName = $data[self::FIELD_KEY];
        $this->searchField = $data[self::SEARCH_FOR_KEY];
        $this->position = $data[self::POSITION_KEY];
        $this->replaceWith = $data[self::REPLACE_WITH_KEY];
        $this->isOverride = $data[self::IS_OVERRIDE_KEY];
        $this->targetField = $data[self::TARGET_FIELD_KEY];
    }


    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param $outputJoinField
     * @return mixed
     */
    public function transform(Collection $collection, array &$metrics, array &$dimensions, array $outputJoinField)
    {
        $reports = $collection->getRows();
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        if (empty($reports)) {
            return;
        }

        if (!$this->getIsOverride() && !empty($this->getTargetField())) {
            $columnNames = array_values($columns);
            if (in_array($this->getTargetField(), $columnNames)) {
                $newFieldName = $this->getTargetField() . '(add in transformation)';
                $this->setTargetField($newFieldName);
            }
        }

        if (!$this->getIsOverride() && !empty($this->getTargetField())) {
            $newFields[$this->getTargetField()] = $this->getTargetField();
            $allColumns = array_merge($columns, $newFields);
            $collection->setColumns($allColumns);
        }

        if (!$this->getIsOverride() && !empty($this->getTargetField())) {
            $newFieldType[$this->getTargetField()] = 'text';
            $allTypes = array_merge($types, $newFieldType);
            $collection->setTypes($allTypes);
        }

        foreach ($reports as $key => $report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                $fieldName = $this->getIsOverride() ? $this->getFieldName() : $this->getTargetField();
                $report[$fieldName] = null;
                $reports[$key] = $report;
                if (!in_array($fieldName, $metrics)) {
                    $metrics[] = $fieldName;
                }
                
                continue;
            }
            
            $value = $report[$this->getFieldName()];
            $replacedValue = $this->replaceText($value, $this->getPosition(), $this->getSearchField(), $this->getReplaceWith());

            $fieldName = $this->getIsOverride() ? $this->getFieldName() : $this->getTargetField();
            if (empty($fieldName)) {
                continue;
            }

            $report[$fieldName] = $replacedValue;
            $reports[$key] = $report;
            if (!in_array($fieldName, $metrics)) {
                $metrics[] = $fieldName;
            }
        }

        $collection->setRows($reports);
    }

    /**
     * @param $originText
     * @param $position
     * @param $searchField
     * @param $replaceWith
     * @return mixed
     */
    protected function replaceText($originText, $position, $searchField, $replaceWith)
    {
        $replaceText = $originText;

        if (empty($replaceText)) {
            return $replaceText;
        }

        switch ($position) {
            case self::ANYWHERE_KEY:
                $replaceText = str_replace($searchField, $replaceWith, $originText);
                break;
            case self::AT_THE_BEGINNING_POSITION_KEY:
                if ($this->startsWith($originText, $searchField)) {
                    $replaceText = substr_replace($originText, $replaceWith, 0, strlen($searchField));
                }
                break;
            case self::AT_THE_END_POSITION_KEY:
                if ($this->endsWith($originText, $searchField)) {
                    $replaceText = substr_replace($originText, $replaceWith, strlen($originText) - strlen($searchField), strlen($searchField));
                }
                break;
            default:
                break;
        }

        return $replaceText;
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
     * @param array $metrics
     * @param array $dimensions
     * @return mixed
     */
    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {

        if (empty($this->getTargetField())) {
            return;
        }

        if (in_array($this->getTargetField(), $metrics) || in_array($this->getTargetField(), $dimensions)) {
            return;
        }

        $metrics[] = $this->getTargetField();
    }

    /**
     * @return mixed
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @return mixed
     */
    public function getSearchField()
    {
        return $this->searchField;
    }

    /**
     * @return mixed
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @return mixed
     */
    public function getReplaceWith()
    {
        return $this->replaceWith;
    }

    /**
     * @return mixed
     */
    public function getIsOverride()
    {
        return $this->isOverride;
    }

    /**
     * @return mixed
     */
    public function getTargetField()
    {
        return $this->targetField;
    }

    /**
     * @param mixed $targetField
     */
    public function setTargetField($targetField)
    {
        $this->targetField = $targetField;
    }


}