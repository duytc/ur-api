<?php


namespace UR\Model\Core;


use UR\Model\ModelInterface;

interface ReportViewTemplateInterface extends ModelInterface
{
    /**
     * @return int
     */
    public function getId();

    /**
     * @param int $id
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
    public function getDataSets();

    /**
     * @param array $dataSets
     * @return self
     */
    public function setDataSets($dataSets);

    /**
     * @return array
     */
    public function getReportViews();

    /**
     * @param array $reportViews
     * @return self
     */
    public function setReportViews($reportViews);

    /**
     * @return array
     */
    public function getJoinConfig();

    /**
     * @param array $joinConfig
     * @return self
     */
    public function setJoinConfig($joinConfig);

    /**
     * @return array
     */
    public function getTransforms();

    /**
     * @param array $transforms
     * @return self
     */
    public function setTransforms($transforms);

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
     * @return array
     */
    public function getShowInTotal();

    /**
     * @param array $showInTotal
     * @return self
     */
    public function setShowInTotal($showInTotal);

    /**
     * @return boolean
     */
    public function isShowDataSetName();

    /**
     * @param boolean $showDataSetName
     * @return self
     */
    public function setShowDataSetName($showDataSetName);

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
     * @return array
     */
    public function getMetrics();

    /**
     * @param array $metrics
     * @return self
     */
    public function setMetrics($metrics);

    /**
     * @return ReportViewTemplateTagInterface[]
     */
    public function getReportViewTemplateTags();

    /**
     * @param ReportViewTemplateTagInterface[] $reportViewTemplateTags
     * @return self
     */
    public function setReportViewTemplateTags($reportViewTemplateTags);

    /**
     * @return array
     */
    public function getCalculatedMetrics();

    /**
     * @param array $calculatedMetrics
     * @return self
     */
    public function setCalculatedMetrics($calculatedMetrics);
}