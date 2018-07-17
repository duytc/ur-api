<?php


namespace UR\Model\Core;


use Doctrine\ORM\PersistentCollection;
use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

interface ReportViewInterface extends ModelInterface
{
    const REPORT_VIEW_DIMENSIONS = 'select_dimensions';
    const REPORT_VIEW_METRICS = 'select_metrics';
    const REPORT_VIEW_TRANSFORMS = 'transforms';
    const REPORT_VIEW_FILTERS = 'filters';
    const REPORT_VIEW_FIELD_TYPES = 'field_types';
    const REPORT_VIEW_FORMATS = 'formats';
    const REPORT_VIEW_JOIN_BY = 'join_by';

    const TOKEN = 'token';
    const LINK = 'link';
    const SHARED_KEYS_CONFIG = 'sharedKeysConfig';

    const SHARE_FIELDS = 'fields';
    const SHARE_DATE_CREATED = 'dateCreated';
    const SHARE_DATE_RANGE = 'dateRange';
    const SHARE_ALLOW_DATES_OUTSIDE = 'allowDatesOutside';

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
     * @return \DateTime
     */
    public function getCreatedDate();

    /**
     * @param \DateTime $createdDate
     * @return self
     */
    public function setCreatedDate($createdDate);

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
    public function getIsShowDataSetName();

    /**
     * @param mixed $isShowDataSetName
     * @return self
     */
    public function setIsShowDataSetName($isShowDataSetName);

    /**
     * @return mixed
     */
    public function getLastActivity();

    /**
     * @param mixed $lastActivity
     * @return self
     */
    public function setLastActivity($lastActivity);

    /**
     * @return mixed
     */
    public function getLastRun();

    /**
     * @param mixed $LastRun
     * @return self
     */
    public function setLastRun($LastRun);

    /**
     * @param mixed $id
     * @return self
     */
    public function setId($id);

    /**
     * @return boolean
     */
    public function isEnableCustomDimensionMetric();

    /**
     * @param boolean $enableCustomDimensionMetric
     * @return self
     */
    public function setEnableCustomDimensionMetric($enableCustomDimensionMetric);

    /**
     * @return ReportViewInterface
     */
    public function getMasterReportView();

    /**
     * @param ReportViewInterface $masterReportView
     * @return self
     */
    public function setMasterReportView($masterReportView);

    /**
     * @return ReportViewInterface[]
     */
    public function getSubReportViews();

    /**
     * @param ReportViewInterface[] $subReportViews
     * @return self
     */
    public function setSubReportViews($subReportViews);

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
    public function isSubView();

    /**
     * @param boolean $subview
     * @return self
     */
    public function setSubView($subview);

    /**
     * @return boolean
     */
    public function isLargeReport();

    /**
     * @param boolean $largeReport
     * @return self
     */
    public function setLargeReport($largeReport);

    /**
     * @return boolean
     */
    public function isAvailableToRun();

    /**
     * @param boolean $availableToRun
     * @return self
     */
    public function setAvailableToRun($availableToRun);

    /**
     * @return boolean
     */
    public function isAvailableToChange();

    /**
     * @param boolean $availableToChange
     * @return self
     */
    public function setAvailableToChange($availableToChange);

    /**
     * @return string
     */
    public function getPreCalculateTable();

    /**
     * @param string $preCalculateTable
     * @return self
     */
    public function setPreCalculateTable($preCalculateTable);

    /**
     * @return bool
     */
    public function isRequireJoin();

    /**
     * @param bool $requireJoin
     * @return self
     */
    public function setRequireJoin($requireJoin);

    /**
     * @return self
     */
    public function setSmallReport();
}