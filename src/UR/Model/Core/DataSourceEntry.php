<?php

namespace UR\Model\Core;

class DataSourceEntry implements DataSourceEntryInterface
{
    protected $id;
    protected $receivedDate;
    protected $isValid;
    protected $isActive;
    protected $path;
    protected $fileName;
    protected $metaData;
    protected $receivedVia;
    protected $hashFile;

    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

    public function __construct()
    {
        $this->isValid = false;
        $this->isActive = true;
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

        return $this;
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

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function isSupportedReceivedViaType($receivedVia)
    {
        return (in_array($receivedVia,
            [
                self::RECEIVED_VIA_UPLOAD,
                self::RECEIVED_VIA_SELENIUM,
                self::RECEIVED_VIA_API,
                self::RECEIVED_VIA_EMAIL
            ]
        ));
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

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getIsActive()
    {
        return $this->isActive;
    }

    /**
     * @inheritdoc
     */
    public function setIsActive($active)
    {
        $this->isActive = $active;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getHashFile()
    {
        return $this->hashFile;
    }

    /**
     * @inheritdoc
     */
    public function setHashFile($hashFile)
    {
        $this->hashFile = $hashFile;
        return $this;
    }
}