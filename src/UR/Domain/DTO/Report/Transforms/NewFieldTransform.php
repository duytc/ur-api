<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Service\DTO\Collection;

abstract class NewFieldTransform extends AbstractTransform implements TransformInterface
{
    const FIELD_NAME_KEY = 'field';
    const TYPE_KEY = 'type';

    /**
     * @var string
     */
    protected $fieldName;

    /**
     * @var string
     */
    protected $type;

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @param null $outputJoinField
     */
    public function transform(Collection $collection, array &$metrics, array &$dimensions, array $outputJoinField)
    {
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        if (!in_array($this->fieldName, $metrics)) {
            $metrics[] = $this->fieldName;
        }

        if (!in_array($this->fieldName, $columns)) {
            $columns[] = $this->fieldName;
            $types[$this->fieldName] = $this->type;
            $collection->setColumns($columns);
            $collection->setTypes($types);
        }
    }
}