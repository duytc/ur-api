<?php

namespace UR\Model\Core;

use DateTime;

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
    protected $totalRow;
    protected $fileExtension;
    private $isDryRun = false;
    protected $removeHistory;

    /** @var bool */
    protected $separable;

    /** @var array */
    protected $chunks;

    /**
     * @var array
     */
    protected $dates;
    /**
     * @var DataSourceInterface
     */
    protected $dataSource;

    protected $importHistories;

    /**
     * @var array
     */
    protected $missingDate;

    /**
     * @var DateTime
     */
    protected $startDate;

    /**
     * @var DateTime
     */
    protected $endDate;

    /**
     * @var bool
     */
    protected $dateRangeBroken;



    public function __construct()
    {
        $this->isValid = false;
        $this->isActive = true;
        $this->dateRangeBroken = false;
        $this->totalRow = 0;
        $this->missingDate = [];
        $this->chunks = [];
        $this->separable = false;
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
                self::RECEIVED_VIA_INTEGRATION,
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

    /**
     * @return boolean|null
     */
    public function isIsDryRun()
    {
        return $this->isDryRun;
    }

    /**
     * @param boolean $isDryRun
     */
    public function setIsDryRun(bool $isDryRun)
    {
        $this->isDryRun = $isDryRun;
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
    public function setTotalRow($totalRow)
    {
        $this->totalRow = $totalRow;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getFileExtension()
    {
        return $this->fileExtension;
    }

    /**
     * @inheritdoc
     */
    public function setFileExtension($fileExtension)
    {
        $this->fileExtension = $fileExtension;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getImportHistories()
    {
        return $this->importHistories;
    }

    /**
     * @inheritdocs
     */
    public function setImportHistories($importHistories)
    {
        $this->importHistories = $importHistories;
        return $this;
    }

    /**
     * @return array
     */
    public function getMissingDate()
    {
        return $this->missingDate;
    }

    /**
     * @param array $missingDate
     * @return self
     */
    public function setMissingDate($missingDate)
    {
        $this->missingDate = $missingDate;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @param DateTime $startDate
     * @return self
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @param DateTime $endDate
     * @return self
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;
        return $this;
    }

    /**
     * @return boolean
     */
    public function isDateRangeBroken()
    {
        return $this->dateRangeBroken;
    }

    /**
     * @param boolean $dateRangeBroken
     * @return self
     */
    public function setDateRangeBroken($dateRangeBroken)
    {
        $this->dateRangeBroken = $dateRangeBroken;
        return $this;
    }

    /**
     * @return array
     */
    public function getDates()
    {
        return $this->dates;
    }

    /**
     * @param array $dates
     * @return self
     */
    public function setDates($dates)
    {
        $this->dates = $dates;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRemoveHistory()
    {
        return $this->removeHistory;
    }

    /**
     * @param mixed $removeHistory
     * @return self
     */
    public function setRemoveHistory($removeHistory)
    {
        $this->removeHistory = $removeHistory;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isSeparable(): bool
    {
        return $this->separable;
    }

    /**
     * @inheritdoc
     */
    public function setSeparable($separable)
    {
        $this->separable = $separable;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getChunks(): array
    {
        return $this->chunks;
    }

    /**
     * @inheritdoc
     */
    public function setChunks($chunks)
    {
        $this->chunks = $chunks;
        return $this;
    }
}