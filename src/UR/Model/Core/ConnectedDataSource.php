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
    protected $lastActivity;
    
    /*
     * this variable to know which linked type, currently we only have augmentation linked type
     * re import data base on linked type when connected data source has augmentation transform
     */
    protected $__linkedType;

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
     * @var int
     */
    protected $totalRow;
    /**
     * @var int
     */
    protected $numChanges;

    /** @var  bool */
    protected $preview;

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
        $this->totalRow = 0;
        $this->numChanges = 0;
        $this->preview = false;
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

    /**
     * @inheritdoc
     */
    public function getLastActivity()
    {
        return $this->lastActivity;
    }

    /**
     * @inheritdoc
     */
    public function setLastActivity($lastActivity)
    {
        $this->lastActivity = $lastActivity;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLinkedType()
    {
        return $this->__linkedType;
    }

    /**
     * @inheritdoc
     */
    public function setLinkedType($_linkedType)
    {
        $this->__linkedType = $_linkedType;
    }

    /**
     * @inheritdoc
     */
    public function getTotalRow()
    {
        return $this->totalRow;
    }

    /**
     * @inheritdoc
     */
    public function setTotalRow(int $totalRow)
    {
        $this->totalRow = $totalRow;
        return $this;
    }

    /**
     * @return int
     */
    public function getNumChanges()
    {
        return $this->numChanges;
    }

    /**
     * @param int $numChanges
     * @return self
     */
    public function setNumChanges($numChanges)
    {
        $this->numChanges = $numChanges;
        return $this;
    }

    /**
     * @return $this
     */
    public function increaseNumChanges()
    {
        ++$this->numChanges;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isPreview()
    {
        return $this->preview;
    }

    /**
     * @inheritdoc
     */
    public function setPreview($preview)
    {
        $this->preview = $preview;

        return $this;
    }
}