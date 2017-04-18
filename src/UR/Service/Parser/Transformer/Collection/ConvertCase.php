<?php


namespace UR\Service\Parser\Transformer\Collection;


use Doctrine\ORM\EntityManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;

class ConvertCase implements CollectionTransformerInterface
{
    const LOWER_CASE_CONVERT = 'lowerCase';
    const UPPER_CASE_CONVERT = 'upperCase';
    const TARGET_FIELD_KEY = 'targetField';
    const IS_OVERRIDE_KEY = 'isOverride';
    const CONVERT_TYPE_KEY = 'type';

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

    /**
     * ConvertCase constructor.
     * @param string $field
     * @param string $convertType
     * @param bool $isOverride
     * @param string $targetField
     */
    public function __construct($field, $convertType, $isOverride = true, $targetField = null)
    {
        $this->field = $field;
        $this->convertType = $convertType;
        $this->isOverride = $isOverride;
        $this->targetField = $targetField;
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
}