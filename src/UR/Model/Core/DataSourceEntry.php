<?php

namespace UR\Model\Core;

class DataSourceEntry implements DataSourceEntryInterface
{
    protected $id;
    protected $receivedDate;
    protected $valid;
    protected $path;
    protected $metaData;
    protected $format;

    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

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
    public function getReceivedDate()
    {
        return $this->receivedDate;
    }

    /**
     * @inheritdoc
     */
    public function setReceivedDate($receivedDate)
    {
        $this->receivedDate = $receivedDate;
    }

    /**
     * @inheritdoc
     */
    public function getValid()
    {
        return $this->valid;
    }

    /**
     * @inheritdoc
     */
    public function setValid($valid)
    {
        $this->valid = $valid;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @inheritdoc
     */
    public function setPath($path)
    {
        $this->path = $path;

        return $this;
    }
    /**
     * @inheritdoc
     */
    public function getMetaData()
    {
        return $this->metaData;
    }

    /**
     * @inheritdoc
     */
    public function setMetaData($metaData)
    {
        $this->metaData = $metaData;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFormat()
    {
        return $this->format;
    }

    /**
     * @inheritdoc
     */
    public function setFormat($format)
    {
        $this->format = $format;
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
    public function setDataSource(DataSourceInterface $dataSource)
    {
        $this->dataSource = $dataSource;

        return $this;
    }
}