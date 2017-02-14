<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Exception\InvalidArgumentException;
use UR\Service\DTO\Collection;

class AddFieldTransform extends NewFieldTransform implements TransformInterface
{
    const PRIORITY = 3;
	const TRANSFORMS_TYPE = 'addField';

    const FIELD_VALUE = 'value';

    protected $value;

    /**
     * AddFieldTransform constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        parent::__construct();

        if (!array_key_exists(self::FIELD_NAME_KEY, $data) || !array_key_exists(self::FIELD_VALUE, $data) || !array_key_exists(self::TYPE_KEY, $data)) {
            throw new InvalidArgumentException('either "fields" or "fieldValue" or "type" is missing');
        }

        $this->fieldName = $data[self::FIELD_NAME_KEY];
        $this->value = $data[self::FIELD_VALUE];
        $this->type = $data[self::TYPE_KEY];
    }

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param $outputJoinField
     * @return mixed|void
     */
    public function transform(Collection $collection,  array &$metrics, array &$dimensions, array $outputJoinField)
    {
        parent::transform($collection, $metrics, $dimensions, $outputJoinField);

        $rows = $collection->getRows();
//        if (is_numeric($this->fieldName)) {
//            $this->fieldName = strval($this->fieldName);
//        }

        $newRows = array_map(function ($row) {
            $row[$this->fieldName] = $this->value;
            return $row;
        }, $rows);

        $collection->setRows($newRows);
    }

    public function getMetricsAndDimensions(array &$metrics, array &$dimensions)
    {
        if (in_array($this->fieldName, $metrics) || in_array($this->fieldName, $dimensions)) {
            return;
        }

        $metrics[] = $this->fieldName;
    }
}