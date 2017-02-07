<?php

namespace UR\Domain\DTO\Report\Transforms;

use Doctrine\Common\Proxy\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class ReplaceTextTransform extends AbstractTransform implements TransformInterface
{

    const PRIORITY = 5;
    const TRANSFORMS_TYPE = 'replaceText';

    const FIELD_KEY = 'field';
    const SEARCH_FOR_KEY = 'searchFor';
    const POSITION_KEY = 'position';
    const REPLACE_WITH_KEY = 'replaceWith';

    const ANYWHERE_KEY = 'anywhere';
    const AT_THE_BEGINNING_POSITION_KEY = 'at the beginning';
    const AT_THE_END_POSITION_KEY = 'at the end';

    protected $fieldName;
    protected $searchField;
    protected $position;
    protected $replaceWith;

    /**
     * ReplaceTextTransform constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        parent::__construct();

        if (!array_key_exists(self::FIELD_KEY, $data) || !array_key_exists(self::SEARCH_FOR_KEY, $data)
            || !array_key_exists(self::POSITION_KEY, $data) || !array_key_exists(self::REPLACE_WITH_KEY, $data)
        ) {
            throw new InvalidArgumentException('either "searchFor" or "position" or "replaceWith" is missing');
        }

        $this->fieldName = $data[self::FIELD_KEY];
        $this->searchField = $data[self::SEARCH_FOR_KEY];
        $this->position = $data[self::POSITION_KEY];
        $this->replaceWith = $data[self::REPLACE_WITH_KEY];
    }


    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param $joinBy
     * @return mixed
     */
    public function transform(Collection $collection, array &$metrics, array &$dimensions, $joinBy = null)
    {
        $reports = $collection->getRows();
        if (empty($reports)) {
            return;
        }

        foreach ($reports as $key => $report) {
            if (!array_key_exists($this->getFieldName(), $report)) {
                continue;
            }
            $value = $report[$this->getFieldName()];
            $report[$this->getFieldName()] = $this->replaceText($value, $this->getPosition(), $this->getSearchField(), $this->getReplaceWith());
            $reports[$key] = $report;
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
        // TODO: Implement getMetricsAndDimensions() method.
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
}