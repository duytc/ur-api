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
    protected $_actions = [];
    protected $lastActivity;
    protected $jobExpirationDate;
    protected $numOfPendingLoad;

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
     * @var DataSetImportJobInterface[]
     */
    protected $dataSetImportJobs;

    public function __construct()
    {
        $this->totalRow = 0;
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
     * @inheritdoc
     */
    public function getDataSetImportJobs()
    {
        return $this->dataSetImportJobs;
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
    public function setDataSetImportJobs(array $dataSetImportJobs)
    {
        $this->dataSetImportJobs = $dataSetImportJobs;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function addDataSetImportJobs(DataSetImportJobInterface $dataSetImportJob)
    {
        if ($this->dataSetImportJobs instanceof Collection) {
            $this->dataSetImportJobs = $this->dataSetImportJobs->toArray();
        }

        if (!is_array($this->dataSetImportJobs)) {
            $this->dataSetImportJobs = [];
        }

        $this->dataSetImportJobs[] = $dataSetImportJob;
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
    public function getJobExpirationDate()
    {
        return $this->jobExpirationDate;
    }

    /**
     * @inheritdoc
     */
    public function setJobExpirationDate($jobExpirationDate)
    {
        $this->jobExpirationDate = $jobExpirationDate;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getNumOfPendingLoad()
    {
        return $this->numOfPendingLoad;
    }

    /**
     * @inheritdoc
     */
    public function setNumOfPendingLoad($numOfPendingLoad)
    {
        $this->numOfPendingLoad = $numOfPendingLoad;
        return $this;
    }
}