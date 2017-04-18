<?php

namespace UR\Model\Core;

class ConnectedDataSource implements ConnectedDataSourceInterface
{
    protected $id;
    protected $name;
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
     * @inheritdoc
     */
    public function setReplayData($replayData)
    {
        $this->replayData = $replayData;

        return $this;
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
    public function getName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        $this->name = $name;

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

        return $this;
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

        return $this;
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

        return $this;
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

        return $this;
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

        return $this;
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

        return $this;
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

        return $this;
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

        return $this;
    }
}