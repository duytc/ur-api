<?php

namespace UR\Model\Core;

class ImportHistory implements ImportHistoryInterface
{
    protected $id;
    protected $createdDate;
    protected $description;

    /**
     * @var ConnectedDataSourceInterface
     */
    protected $connectedDataSource;

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
    }

    /**
     * @inheritdoc
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * @inheritdoc
     */
    public function setDescription($description)
    {
        $this->description = $description;
    }

    /**
     * @inheritdoc
     */
    public function getConnectedDataSource()
    {
        return $this->connectedDataSource;
    }

    /**
     * @inheritdoc
     */
    public function setConnectedDataSource($connectedDataSource)
    {
        $this->connectedDataSource = $connectedDataSource;
    }
}