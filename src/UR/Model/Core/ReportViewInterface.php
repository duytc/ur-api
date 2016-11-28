<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

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
     * @return string
     */
    public function getJoinBy();

    /**
     * @param string $joinBy
     * @return self
     */
    public function setJoinBy($joinBy);

    /**
     * @return array
     */
    public function getTransforms();

    /**
     * @param array $transform
     * @return self
     */
    public function setTransforms($transform);

    /**
     * @return PublisherInterface
     */
    public function getPublisher();

    /**
     * @param PublisherInterface $publisher
     * @return self
     */
    public function setPublisher($publisher);

    /**
     * @return array
     */
    public function getWeightedCalculations();

    /**
     * @param array $weightedCalculations
     * @return self
     */
    public function setWeightedCalculations($weightedCalculations);

    /**
     * @return string
     */
    public function getSharedKey();

    /**
     * @param string $sharedKey
     * @return self
     */
    public function setSharedKey($sharedKey);
}