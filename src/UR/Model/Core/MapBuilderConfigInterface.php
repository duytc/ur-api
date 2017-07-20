<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;

interface MapBuilderConfigInterface extends ModelInterface
{
    /**
     * @param int $id
     * @return self
     */
    public function setId($id);

    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $name
     * @return self
     */
    public function setName($name);

    /**
     * @return array
     */
    public function getMapFields();

    /**
     * @param array $mapFields
     * @return self
     */
    public function setMapFields($mapFields);

    /**
     * @return array
     */
    public function getFilters();

    /**
     * @param array $filters
     * @return self
     */
    public function setFilters($filters);

    /**
     * @return boolean
     */
    public function isLeftSide();

    /**
     * @param boolean $leftSide
     * @return self
     */
    public function setLeftSide($leftSide);

    /**
     * @return DataSetInterface
     */
    public function getDataSet();

    /**
     * @param DataSetInterface $dataSet
     * @return self
     */
    public function setDataSet($dataSet);

    /**
     * @return DataSetInterface
     */
    public function getMapDataSet();

    /**
     * @param DataSetInterface $mapDataSet
     * @return self
     */
    public function setMapDataSet($mapDataSet);
}