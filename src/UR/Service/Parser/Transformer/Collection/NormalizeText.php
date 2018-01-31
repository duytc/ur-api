<?php


namespace UR\Service\Parser\Transformer\Collection;


use Doctrine\ORM\EntityManagerInterface;
use SplDoublyLinkedList;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;

class NormalizeText implements CollectionTransformerInterface, CollectionTransformerJsonConfigInterface
{
    const IS_OVERRIDE_KEY = 'isOverride';
    const TARGET_FIELD_KEY = 'targetField';
    const NUMBER_REMOVED_KEY = 'numberRemoved';
    const NON_ALPHA_NUMERIC_REMOVED_KEY = 'alphabetCharacterRemoved';
    const WHITE_SPACE_REMOVED_KEY = 'dashesRemoved';
    const NON_ASCII_REMOVED_KEY = 'nonAsciiRemoved';

    /**
     * @var string
     */
    protected $field;
    /**
     * @var bool
     */
    protected $isOverride;

    /**
     * @var string
     */

    protected $targetField;
    /**
     * @var bool
     */
    protected $numbersRemoved;

    /**
     * @var bool
     */
    protected $alphabetCharacterRemoved;

    /**
     * @var bool
     */
    protected $dashesRemoved;

    /**
     * @var bool
     */
    protected $nonAsciiRemoved;

    /**
     * NormalizeText constructor.
     * @param string $field
     * @param bool $isOverride
     * @param string $targetField
     * @param bool $numbersRemoved
     * @param bool $alphabetCharacterRemoved
     * @param bool $dashesRemoved
     * @param bool $nonAsciiRemoved
     */
    public function __construct($field, $isOverride, $targetField, $numbersRemoved = false, $alphabetCharacterRemoved = false, $dashesRemoved = false, $nonAsciiRemoved = false)
    {
        $this->field = $field;
        $this->isOverride = $isOverride;
        $this->targetField = $targetField;
        $this->numbersRemoved = $numbersRemoved;
        $this->alphabetCharacterRemoved = $alphabetCharacterRemoved;
        $this->dashesRemoved = $dashesRemoved;
        $this->nonAsciiRemoved = $nonAsciiRemoved;
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
            unset($row);
        }

        unset($collection, $rows, $row);
        return new Collection($columns, $newRows, $types);
    }

    private function getValue(array $row)
    {
        if (!array_key_exists($this->field, $row)) {
            return null;
        }

        $result = $row[$this->field];

        if ($this->numbersRemoved) {
            $result = preg_replace('/[0-9]/', '', $result);
        }

        if ($this->dashesRemoved) {
            $result = preg_replace('/\s*/', '', $result);
        }

        if ($this->alphabetCharacterRemoved) {
            $result = preg_replace('/[^a-z0-9]/', '', $result);
        }

        if ($this->nonAsciiRemoved) {
            //remove all non asscii charactor
            $result = preg_replace("/[^A-Za-z0-9 ]/", '', $result);
        }

        return $result;
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
        return self::TRANSFORM_PRIORITY_NORMALIZE_TEXT;
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
     * @param string $field
     */
    public function setField(string $field)
    {
        $this->field = $field;
    }

    /**
     * @return boolean
     */
    public function isIsOverride(): bool
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

    /**
     * @return boolean
     */
    public function isNumbersRemoved(): bool
    {
        return $this->numbersRemoved;
    }

    /**
     * @param boolean $numbersRemoved
     */
    public function setNumbersRemoved(bool $numbersRemoved)
    {
        $this->numbersRemoved = $numbersRemoved;
    }

    /**
     * @return boolean
     */
    public function isAlphabetCharacterRemoved(): bool
    {
        return $this->alphabetCharacterRemoved;
    }

    /**
     * @param boolean $alphabetCharacterRemoved
     */
    public function setAlphabetCharacterRemoved(bool $alphabetCharacterRemoved)
    {
        $this->alphabetCharacterRemoved = $alphabetCharacterRemoved;
    }

    /**
     * @return boolean
     */
    public function isDashesRemoved(): bool
    {
        return $this->dashesRemoved;
    }

    /**
     * @param boolean $dashesRemoved
     */
    public function setDashesRemoved(bool $dashesRemoved)
    {
        $this->dashesRemoved = $dashesRemoved;
    }

    /**
     * @return boolean
     */
    public function isNonAsciiRemoved(): bool
    {
        return $this->nonAsciiRemoved;
    }

    /**
     * @param boolean $nonAsciiRemoved
     */
    public function setNonAsciiRemoved(bool $nonAsciiRemoved)
    {
        $this->nonAsciiRemoved = $nonAsciiRemoved;
    }

    public function getJsonTransformFieldsConfig()
    {
        $transformFields = [];
        $transformFields[self::FIELD_KEY] = $this->field;
        $transformFields[self::IS_OVERRIDE_KEY] = $this->isOverride;
        $transformFields[self::TARGET_FIELD_KEY] = $this->targetField;
        $transformFields[self::NUMBER_REMOVED_KEY] = $this->numbersRemoved;
        $transformFields[self::NON_ALPHA_NUMERIC_REMOVED_KEY] = $this->alphabetCharacterRemoved;
        $transformFields[self::WHITE_SPACE_REMOVED_KEY] = $this->dashesRemoved;
        return $transformFields;
    }
}