<?php

namespace UR\Model\Core;

use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\UserEntityInterface;

class DataSet implements DataSetInterface
{
    protected $id;
    protected $name;
    protected $dimensions;
    protected $metrics;
    protected $createdDate;
    protected $allowOverwriteExistingData;
    protected $_actions = [];
    protected $lastActivity;

    /** @var UserEntityInterface */
    protected $publisher;

    /**
     * @var int
     */
    protected $totalRow;

    /**
     * @var ConnectedDataSourceInterface[]
     */
    protected $connectedDataSources;

    /**
     * @var LinkedMapDataSetInterface[]
     */
    protected $linkedMapDataSets;

    protected $noConnectedDataSourceChanges;
    protected $noChanges;

    public function __construct()
    {
        $this->totalRow = 0;
        $this->noChanges = 0;
        $this->noConnectedDataSourceChanges = 0;
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
    public function setId($id)
    {
        $this->id = $id;
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
    public function getDimensions()
    {
        return is_array($this->dimensions) ? $this->dimensions : [];
    }

    /**
     * @inheritdoc
     */
    public function setDimensions($dimensions)
    {
        $this->dimensions = $dimensions;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getMetrics()
    {
        return is_array($this->metrics) ? $this->metrics : [];
    }

    /**
     * @inheritdoc
     */
    public function setMetrics($metrics)
    {
        $this->metrics = $metrics;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getCreatedDate()
    {
        return $this->createdDate;
    }

    /**
     * @inheritdoc
     */
    public function setCreatedDate($createdDate)
    {
        $this->createdDate = $createdDate;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPublisherId()
    {
        if (!$this->publisher) {
            return null;
        }

        return $this->publisher->getId();
    }

    /**
     * @inheritdoc
     */
    public function getPublisher()
    {
        return $this->publisher;
    }

    /**
     * @inheritdoc
     */
    public function setPublisher(PublisherInterface $publisher)
    {
        $this->publisher = $publisher->getUser();
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getConnectedDataSources()
    {
        return $this->connectedDataSources;
    }

    /**
     * @inheritdoc
     */
    public function setConnectedDataSources($connectedDataSources)
    {
        $this->connectedDataSources = $connectedDataSources;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getActions()
    {
        return $this->_actions;
    }

    /**
     * @inheritdoc
     */
    public function setActions($actions)
    {
        $this->_actions = $actions;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAllowOverwriteExistingData()
    {
        return $this->allowOverwriteExistingData;
    }

    /**
     * @inheritdoc
     */
    public function setAllowOverwriteExistingData($allowOverwriteExistingData)
    {
        $this->allowOverwriteExistingData = $allowOverwriteExistingData;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getAllDimensionMetrics()
    {
        return array_merge($this->getDimensions(), $this->getMetrics());
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
     * @return int
     */
    public function getTotalRow()
    {
        return $this->totalRow;
    }

    /**
     * @param int $totalRow
     * @return self
     */
    public function setTotalRow($totalRow)
    {
        $this->totalRow = $totalRow;
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
     * @inheritdoc
     */
    public function getNoConnectedDataSourceChanges()
    {
        return $this->noConnectedDataSourceChanges;
    }

    /**
     * @inheritdoc
     */
    public function setNoConnectedDataSourceChanges($noConnectedDataSourceChanges)
    {
        $this->noConnectedDataSourceChanges = $noConnectedDataSourceChanges;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getNoChanges()
    {
        return $this->noChanges;
    }

    /**
     * @inheritdoc
     */
    public function setNoChanges($noChanges)
    {
        $this->noChanges = $noChanges;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function increaseNoChanges($noChanges = 1)
    {
        $this->noChanges += $noChanges;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function decreaseNoChanges($noChanges = 1)
    {
        // avoid negative remaining value
        $this->noChanges = ($this->noChanges > $noChanges) ? ($this->noChanges - $noChanges) : 0;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function increaseNoConnectedDataSourceChanges($noChanges = 1)
    {
        $this->noConnectedDataSourceChanges += $noChanges;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function decreaseNoConnectedDataSourceChanges($noChanges = 1)
    {
        // avoid negative remaining value
        $this->noConnectedDataSourceChanges = ($this->noConnectedDataSourceChanges > $noChanges) ? ($this->noConnectedDataSourceChanges - $noChanges) : 0;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function hasNonUpToDateMappedDataSets()
    {
        if (!$this->connectedDataSources instanceof Collection && !is_array($this->connectedDataSources)) {
            return false;
        }

        foreach ($this->connectedDataSources as $connectedDataSource) {
            if ($this->hasNonUpToDateMappedDataSetsByConnectedDataSource($connectedDataSource)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function hasNonUpToDateMappedDataSetsByConnectedDataSource(ConnectedDataSourceInterface $connectedDataSource)
    {
        $linkedMapDataSets = $connectedDataSource->getLinkedMapDataSets();
        if (!$this->linkedMapDataSets instanceof Collection && !is_array($this->linkedMapDataSets)) {
            return false;
        }

        foreach ($linkedMapDataSets as $linkedMapDataSet) {
            $mapDataSet = $linkedMapDataSet->getMapDataSet();
            if ($mapDataSet->getNoChanges() > 0 || $mapDataSet->getNoConnectedDataSourceChanges() > 0) {
                return true;
            }
        }

        return false;
    }
}