<?php


namespace UR\Service\Parser\Transformer\Collection;


use Doctrine\ORM\EntityManagerInterface;
use SplDoublyLinkedList;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;

class ConvertCase implements CollectionTransformerInterface, CollectionTransformerJsonConfigInterface
{
    const LOWER_CASE_CONVERT = 'lowerCase';
    const UPPER_CASE_CONVERT = 'upperCase';
    const TARGET_FIELD_KEY = 'targetField';
    const IS_OVERRIDE_KEY = 'isOverride';
    const CONVERT_TYPE_KEY = 'type';
    const AUTO_NORMALIZE_TEXT = 'autoNormalizeText';

    /**
     * @var string
     */
    protected $field;
    /**
     * @var string
     */
    protected $convertType;

    /**
     * @var bool
     */
    protected $isOverride;

    /**
     * @var string
     */
    protected $targetField;

    protected $autoNormalizeText;
    protected $preTransforms = [];

    /**
     * ConvertCase constructor.
     * @param string $field
     * @param string $convertType
     * @param bool $isOverride
     * @param string $targetField
     * @param bool $autoNormalizeText
     */
    public function __construct($field, $convertType, $isOverride = true, $targetField = null, $autoNormalizeText = false)
    {
        $this->field = $field;
        $this->convertType = $convertType;
        $this->isOverride = $isOverride;
        $this->targetField = $targetField;
        $this->autoNormalizeText = $autoNormalizeText;

        if ($this->autoNormalizeText) {
            $normalizeTextTransformConfig = [];
            $normalizeTextTransform = new NormalizeText($this->field, true, null, false, false, false, true);
            $normalizeTextTransformConfig[] = $normalizeTextTransform;
            $this->preTransforms = $normalizeTextTransformConfig;
        }
    }

    /**
     * @param Collection $collection
     * @param EntityManagerInterface|null $em
     * @param ConnectedDataSourceInterface|null $connectedDataSource
     * @param array $fromDateFormats
     * @param array $mapFields
     * @return Collection
     */
    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = [], $mapFields = [])
    {
        // to do Normalize text to remove non alphanumeric first and then do convert case
        $collection = $this->preTransform($collection, $em, $connectedDataSource, $fromDateFormats, $mapFields);

        $rows = $collection->getRows();
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        if (!$this->isOverride) {
            $columns[] = $this->targetField;
        }

        if ($rows->count() < 1) {
            return $collection;
        }

        $newRows = new SplDoublyLinkedList();
        foreach ($rows as $row) {
            if (!array_key_exists($this->field, $row)) {
                return $collection;
            }

            if ($this->isOverride) {
                $row[$this->field] = $this->getValue($row);
            } else {
                $row[$this->targetField] = $this->getValue($row);
            }

            $newRows->push($row);
            unset ($row);
        }

        unset($collection, $row, $rows);
        return new Collection($columns, $newRows, $types);
    }

    private function preTransform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = [], $mapFields = [])
    {
        foreach ($this->preTransforms as $transform) {
            $collection = $transform->transform($collection, $em , $connectedDataSource, $fromDateFormats, $mapFields);
        }

         return $collection;
    }

    private function getValue(array $row)
    {
        if (!array_key_exists($this->field, $row) || is_null($row[$this->field])) {
            return null;
        }

        //$value = $row[$this->field];

        if ($this->convertType == self::LOWER_CASE_CONVERT) {
            return isset($row[$this->field]) ? strtolower($row[$this->field]) : null;
        }

        return isset($row[$this->field]) ? strtoupper($row[$this->field]) : null;
    }

    /**
     * The idea is that some column transformers should run before others to avoid conflicts
     * i.e usually you would want to group columns before adding calculated fields
     * The parser config should read this priority value and order the transformers based on this value
     * Lower numbers mean higher priority, for example -10 is higher than 0.
     * Maybe we should allow the end user to override this if they know what they are doing
     *
     * @return int
     */
    public function getDefaultPriority()
    {
        return self::TRANSFORM_PRIORITY_CONVERT_CASE;
    }

    /**
     * validate if transform is valid
     *
     * @return bool
     * @throws \Exception if error
     */
    public function validate()
    {
        // TODO: Implement validate() method.
    }

    /**
     * @return string
     */
    public function getTargetField()
    {
        return $this->targetField;
    }

    /**
     * @return string
     */
    public function getField(): string
    {
        return $this->field;
    }

    /**
     * @return string
     */
    public function getConvertType(): string
    {
        return $this->convertType;
    }

    /**
     * @return boolean
     */
    public function isIsOverride(): bool
    {
        return $this->isOverride;
    }

    /**
     * @param string $field
     */
    public function setField(string $field)
    {
        $this->field = $field;
    }

    public function getJsonTransformFieldsConfig()
    {
        $transformFields = [];
        $transformFields[self::FIELD_KEY] = $this->field;
        $transformFields[self::IS_OVERRIDE_KEY] = $this->isOverride;
        $transformFields[self::TARGET_FIELD_KEY] = $this->targetField;
        $transformFields[self::TYPE_KEY] = $this->convertType;
        return $transformFields;
    }
}