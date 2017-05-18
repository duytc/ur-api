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
     * @param array $outputJoinField
     * @return mixed
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

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @param string $fieldName
     * @return self
     */
    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return self
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }
}