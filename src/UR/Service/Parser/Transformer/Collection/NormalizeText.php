<?php


namespace UR\Service\Parser\Transformer\Collection;


use Doctrine\ORM\EntityManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;

class NormalizeText implements CollectionTransformerInterface
{
    const IS_OVERRIDE_KEY = 'isOverride';
    const TARGET_FIELD_KEY = 'targetField';
    const NUMBER_REMOVED_KEY = 'numberRemoved';
    const ALPHABET_CHARACTER_REMOVED_KEY = 'alphabetCharacterRemoved';
    const DASHES_REMOVED_KEY = 'dashesRemoved';

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
     * NormalizeText constructor.
     * @param string $field
     * @param bool $isOverride
     * @param string $targetField
     * @param bool $numbersRemoved
     * @param bool $alphabetCharacterRemoved
     * @param bool $dashesRemoved
     */
    public function __construct($field, $isOverride, $targetField, $numbersRemoved = false, $alphabetCharacterRemoved = false, $dashesRemoved = false)
    {
        $this->field = $field;
        $this->isOverride = $isOverride;
        $this->targetField = $targetField;
        $this->numbersRemoved = $numbersRemoved;
        $this->alphabetCharacterRemoved = $alphabetCharacterRemoved;
        $this->dashesRemoved = $dashesRemoved;
    }


    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null)
    {
        $rows = $collection->getRows();
        $columns = $collection->getColumns();
        $types = $collection->getTypes();

        if (!$this->isOverride) {
            $columns[] = $this->targetField;
        }

        if (count($rows) < 1) {
            return $collection;
        }

        foreach ($rows as &$row) {
            if (!array_key_exists($this->field, $row)) {
                return $collection;
            }

            if ($this->isOverride) {
                $row[$this->field] = $this->getValue($row);
            } else {
                $row[$this->targetField] = $this->getValue($row);
            }
        }

        return new Collection($columns, $rows, $types);
    }

    private function getValue(array $row)
    {
        if (!array_key_exists($this->field, $row)) {
            return null;
        }

        $total = $this->numbersRemoved + ($this->alphabetCharacterRemoved << 1) + ($this->dashesRemoved << 2);
        switch ($total) {
            case 0:
                return preg_replace('/[^-a-z0-9]/i', '', $row[$this->field]);
            case 1:
                return preg_replace('/[^-a-z]/i', '', $row[$this->field]);
            case 2:
                return preg_replace('/[^-0-9]/i', '', $row[$this->field]);
            case 3:
                return preg_replace('/[^-]/i', '', $row[$this->field]);
            case 4:
                return preg_replace('/[^a-z0-9]/i', '', $row[$this->field]);
            case 5:
                return preg_replace('/[^a-z]/i', '', $row[$this->field]);
            case 6:
                return preg_replace('/[^0-9]/i', '', $row[$this->field]);
            case 7:
                $temp = preg_replace('/[^-a-z0-9]/i', '', $row[$this->field]);
                return preg_replace('/[-a-z0-9]/i', '', $temp);
            default:
                return $row[$this->field];
        }
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
}