<?php


namespace UR\Model\Core;


class MapBuilderConfig implements MapBuilderConfigInterface
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $mapFields;

    /**
     * @var array
     */
    protected $filters;

    /**
     * @var bool
     */
    protected $leftSide;

    /**
     * @var DataSetInterface
     */
    protected $dataSet;

    /**
     * @var DataSetInterface
     */
    protected $mapDataSet;

    /**
     * MapBuilderConfig constructor.
     */
    public function __construct()
    {
    }


    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return array
     */
    public function getMapFields()
    {
        return $this->mapFields;
    }

    /**
     * @param array $mapFields
     * @return self
     */
    public function setMapFields($mapFields)
    {
        $this->mapFields = $mapFields;
        return $this;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param array $filters
     * @return self
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isLeftSide()
    {
        return $this->leftSide;
    }

    /**
     * @param boolean $leftSide
     * @return self
     */
    public function setLeftSide($leftSide)
    {
        $this->leftSide = $leftSide;
        return $this;
    }

    /**
     * @return DataSetInterface
     */
    public function getDataSet()
    {
        return $this->dataSet;
    }

    /**
     * @param DataSetInterface $dataSet
     * @return self
     */
    public function setDataSet($dataSet)
    {
        $this->dataSet = $dataSet;
        return $this;
    }

    /**
     * @return DataSetInterface
     */
    public function getMapDataSet()
    {
        return $this->mapDataSet;
    }

    /**
     * @param DataSetInterface $mapDataSet
     * @return self
     */
    public function setMapDataSet($mapDataSet)
    {
        $this->mapDataSet = $mapDataSet;
        return $this;
    }
}