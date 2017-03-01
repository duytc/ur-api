<?php


namespace UR\Model\Core;


use Doctrine\ORM\PersistentCollection;
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
     * @return string
     */
    public function getAlias();

    /**
     * @param string $alias
     * @return self
     */
    public function setAlias($alias);

    /**
     * @return array
     */
    public function getJoinBy();

    /**
     * @param array $joinBy
     * @return self
     */
    public function setJoinBy(array $joinBy);

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
     * @return array
     */
    public function getMetrics();

    /**
     * @param array $metrics
     * @return self
     */
    public function setMetrics($metrics);

    /**
     * @return array
     */
    public function getFieldTypes();

    /**
     * @param array $fieldTypes
     * @return self
     */
    public function setFieldTypes($fieldTypes);

    /**
     * @return array
     */
    public function getDimensions();

    /**
     * @param array $dimensions
     * @return self
     */
    public function setDimensions($dimensions);

    /**
     * @return boolean
     */
    public function isMultiView();

    /**
     * @param boolean $multiView
     * @return self
     */
    public function setMultiView($multiView);

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
     * @return array
     */
    public function getSharedKeysConfig();

    /**
     * @param array $sharedKeysConfig
     * @return self
     */
    public function setSharedKeysConfig($sharedKeysConfig);

    /**
     * @return array
     */
    public function getShowInTotal();

    /**
     * @param array $showInTotal
     * @return self
     */
    public function setShowInTotal($showInTotal);

    /**
     * @return array
     */
    public function getFormats();

    /**
     * @param array $formats
     * @return self
     */
    public function setFormats($formats);

    /**
     * @return boolean
     */
    public function isSubReportsIncluded();

    /**
     * @param boolean $subReportsIncluded
     * @return self
     */
    public function setSubReportsIncluded($subReportsIncluded);

    /**
     * @return PersistentCollection
     */
    public function getReportViewMultiViews();

    /**
     * @param mixed $reportViewMultiViews
     * @return self
     */
    public function setReportViewMultiViews($reportViewMultiViews);

    /**
     * @return PersistentCollection
     */
    public function getReportViewDataSets();

    /**
     * @param mixed $reportViewDataSets
     * @return self
     */
    public function setReportViewDataSets($reportViewDataSets);

    /**
     * @return bool
     */
    public function isUserReorderTransformsAllowed();

    /**
     * @param mixed $userReorderTransformsAllowed
     * @return self
     */
    public function setUserReorderTransformsAllowed($userReorderTransformsAllowed);

    /**
     * @return bool
     */
    public function getIsShowDataSetName();

    /**
     * @param mixed $isShowDataSetName
     * @return self
     */
    public function setIsShowDataSetName($isShowDataSetName);
}