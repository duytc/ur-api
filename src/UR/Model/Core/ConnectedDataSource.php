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
     * @var bool
     */
    protected $userReorderTransformsAllowed;

    /** @var  bool $replayData */
    protected $replayData;

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

    /**
     * @var ConnectedDataSourceCollectionTransform
     */
    protected $collectionTransforms;

    /**
     * @var ConnectedDataSourceColumnTransform
     */
    protected $columnTransforms;

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
     * @return mixed
     */
    public function getCollectionTransforms()
    {
        $this->collectionTransforms = new ConnectedDataSourceCollectionTransform($this->transforms);
        return $this->collectionTransforms;
    }

    /**
     * @return mixed
     */
    public function getColumnTransforms()
    {
        $this->columnTransforms = new ConnectedDataSourceColumnTransform($this->transforms);
        return $this->columnTransforms;
    }

    /**
     * @return bool
     */
    public function isUserReorderTransformsAllowed()
    {
        return $this->userReorderTransformsAllowed;
    }

    /**
     * @param bool $userReorderTransformsAllowed
     * @return self
     */
    public function setUserReorderTransformsAllowed($userReorderTransformsAllowed)
    {
        $this->userReorderTransformsAllowed = $userReorderTransformsAllowed;
        return $this;
    }
}