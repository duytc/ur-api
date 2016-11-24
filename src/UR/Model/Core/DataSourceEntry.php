<?php

namespace UR\Model\Core;

class DataSourceEntry implements DataSourceEntryInterface
{
    protected $id;
    protected $receivedDate;
    protected $isValid;
    protected $path;
    protected $fileName;
    protected $metaData;
    protected $receivedVia;
    protected $autoImport;

    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

    public function __construct()
    {
        $this->isValid = false;
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
    public function getIsValid()
    {
        return $this->isValid;
    }

    /**
     * @inheritdoc
     */
    public function setIsValid($isValid)
    {
        $this->isValid = $isValid;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAutoImport()
    {
        return $this->autoImport;
    }

    /**
     * @param mixed $autoImport
     * @return self
     */
    public function setAutoImport($autoImport)
    {
        $this->autoImport = $autoImport;
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

    /**
     * @inheritdoc
     */
    public function getReceivedVia()
    {
        return $this->receivedVia;
    }

    /**
     * @inheritdoc
     */
    public function setReceivedVia($receivedVia)
    {
        $this->receivedVia = $receivedVia;
    }

    /**
     * @inheritdoc
     */
    public function getFileName()
    {
        return $this->fileName;
    }

    /**
     * @inheritdoc
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
    }
}