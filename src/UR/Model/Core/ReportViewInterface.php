<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;

interface ReportViewInterface extends ModelInterface
{
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
    public function getDataSets();

    /**
     * @param array $dataSets
     * @return self
     */
    public function setDataSets($dataSets);

    /**
     * @return array
     */
    public function getJoinBy();

    /**
     * @param array $joinBy
     * @return self
     */
    public function setJoinBy($joinBy);

    /**
     * @return array
     */
    public function getTransform();

    /**
     * @param array $transform
     * @return self
     */
    public function setTransform($transform);
}