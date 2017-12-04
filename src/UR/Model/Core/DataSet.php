<?php

namespace UR\Model\Core;

use Doctrine\Common\Collections\Collection;
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
    /** $_actions is internal use only */
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

    /**
     * @var int
     */
    protected $numConnectedDataSourceChanges;

    /**
     * @var int
     */
    protected $numChanges;

    /**
     * @var bool
     */
    protected $mapBuilderEnabled;

    /**
     * @var MapBuilderConfigInterface[]
     */
    protected $mapBuilderConfigs;

    /** @var AutoOptimizationConfigDataSetInterface[] */
    protected $autoOptimizationConfigDataSets;

    public function __construct()
    {
        $this->totalRow = 0;
        $this->numChanges = 0;
        $this->numConnectedDataSourceChanges = 0;
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
    public function getNumConnectedDataSourceChanges()
    {
        return $this->numConnectedDataSourceChanges;
    }

    /**
     * @inheritdoc
     */
    public function setNumConnectedDataSourceChanges($numConnectedDataSourceChanges)
    {
        $this->numConnectedDataSourceChanges = $numConnectedDataSourceChanges;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getNumChanges()
    {
        return $this->numChanges;
    }

    /**
     * @inheritdoc
     */
    public function setNumChanges($numChanges)
    {
        $this->numChanges = $numChanges;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isMapBuilderEnabled()
    {
        return $this->mapBuilderEnabled;
    }

    /**
     * @param boolean $mapBuilderEnabled
     * @return self
     */
    public function setMapBuilderEnabled($mapBuilderEnabled)
    {
        $this->mapBuilderEnabled = $mapBuilderEnabled;
        return $this;
    }

    /**
     * @return array
     */
    public function getMapBuilderConfigs()
    {
        return $this->mapBuilderConfigs;
    }

    /**
     * @param array $mapBuilderConfigs
     * @return self
     */
    public function setMapBuilderConfigs($mapBuilderConfigs)
    {
        $this->mapBuilderConfigs = $mapBuilderConfigs;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function increaseNumChanges($numChanges = 1)
    {
        $this->numChanges += $numChanges;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function decreaseNumChanges($numChanges = 1)
    {
        // avoid negative remaining value
        $this->numChanges = ($this->numChanges > $numChanges) ? ($this->numChanges - $numChanges) : 0;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function increaseNumConnectedDataSourceChanges($numChanges = 1)
    {
        $this->numConnectedDataSourceChanges += $numChanges;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function decreaseNumConnectedDataSourceChanges($numChanges = 1)
    {
        // avoid negative remaining value
        $this->numConnectedDataSourceChanges = ($this->numConnectedDataSourceChanges > $numChanges) ? ($this->numConnectedDataSourceChanges - $numChanges) : 0;

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
            if ($mapDataSet->getNumChanges() > 0 || $mapDataSet->getNumConnectedDataSourceChanges() > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function getAutoOptimizationConfigDataSets()
    {
        return $this->autoOptimizationConfigDataSets;
    }

    /**
     * @inheritdoc
     */
    public function setAutoOptimizationConfigDataSets($autoOptimizationConfigDataSets)
    {
        $this->autoOptimizationConfigDataSets = $autoOptimizationConfigDataSets;

        return $this;
    }
}