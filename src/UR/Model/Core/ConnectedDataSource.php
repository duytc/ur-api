<?php

namespace UR\Model\Core;

class ConnectedDataSource implements ConnectedDataSourceInterface
{
    protected $id;
    protected $mapFields;
    protected $filters;
    protected $transforms;
    protected $requires;
    protected $alertSetting;

    /**
     * @var array
     */
    protected $temporaryFields;

    /** @var  bool $replayData */
    protected $replayData;

    /**
     * @var LinkedMapDataSetInterface[]
     */
    protected $linkedMapDataSets;

    /**
     * @return boolean
     */
    public function isReplayData()
    {
        return $this->replayData;
    }

    /**
     * @param boolean $replayData
     */
    public function setReplayData($replayData)
    {
        $this->replayData = $replayData;
    }


    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

    /**
     * @var DataSetInterface
     */
    protected $dataSet;

    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * set id, be careful. Current only for dryRun mode only
     *
     * @param int $id
     * @return mixed
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMapFields()
    {
        return $this->mapFields;
    }

    /**
     * @inheritdoc
     */
    public function setMapFields($mapFields)
    {
        $this->mapFields = $mapFields;
    }

    /**
     * @inheritdoc
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @inheritdoc
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
    }

    /**
     * @inheritdoc
     */
    public function getTransforms()
    {
        return $this->transforms;
    }

    /**
     * @inheritdoc
     */
    public function setTransforms($transforms)
    {
        $this->transforms = $transforms;
    }

    /**
     * @inheritdoc
     */
    public function getRequires()
    {
        return $this->requires;
    }

    /**
     * @inheritdoc
     */
    public function setRequires($requires)
    {
        $this->requires = $requires;
    }

    /**
     * @inheritdoc
     */
    public function getAlertSetting()
    {
        return $this->alertSetting;
    }

    /**
     * @inheritdoc
     */
    public function setAlertSetting($alertSetting)
    {
        $this->alertSetting = $alertSetting;
    }

    /**
     * @return array
     */
    public function getTemporaryFields()
    {
        return $this->temporaryFields;
    }

    /**
     * @param array $temporaryFields
     * @return self
     */
    public function setTemporaryFields($temporaryFields)
    {
        $this->temporaryFields = $temporaryFields;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDataSource()
    {
        return $this->dataSource;
    }

    /**
     * @inheritdoc
     */
    public function setDataSource($dataSource)
    {
        $this->dataSource = $dataSource;
    }

    /**
     * @inheritdoc
     */
    public function getDataSet()
    {
        return $this->dataSet;
    }

    /**
     * @inheritdoc
     */
    public function setDataSet($dataSet)
    {
        $this->dataSet = $dataSet;
    }

    /**
     * @inheritdoc
     */
    public function getLinkedMapDataSets()
    {
        return $this->linkedMapDataSets;
    }

    /**
     * @inheritdoc
     */
    public function setLinkedMapDataSets(array $linkedMapDataSets)
    {
        $this->linkedMapDataSets = $linkedMapDataSets;
    }
}