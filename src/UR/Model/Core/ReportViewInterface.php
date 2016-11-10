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
    public function getJoinedFields();

    /**
     * @param array $joinedFields
     * @return self
     */
    public function setJoinedFields($joinedFields);

    /**
     * @return array
     */
    public function getTransforms();

    /**
     * @param array $transforms
     * @return self
     */
    public function setTransforms($transforms);
}