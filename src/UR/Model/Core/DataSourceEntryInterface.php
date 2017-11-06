<?php

namespace UR\Model\Core;

use DateTime;
use UR\Model\ModelInterface;

interface DataSourceEntryInterface extends ModelInterface
{
    const RECEIVED_VIA_UPLOAD = 'upload';
    const RECEIVED_VIA_INTEGRATION = 'integration';
    const RECEIVED_VIA_API = 'api';
    const RECEIVED_VIA_EMAIL = 'email';

    /**
     * @return DateTime
     */
    public function getReceivedDate();

    /**
     * @param mixed $receivedDate
     * @return self
     */
    public function setReceivedDate($receivedDate);

    /**
     * @return boolean
     */
    public function getIsValid();

    /**
     * @param boolean $valid
     * @return self
     */
    public function setIsValid($valid);

    /**
     * @return boolean
     */
    public function getIsActive();

    /**
     * @param boolean $active
     * @return self
     */
    public function setIsActive($active);

    /**
     * @return string
     */
    public function getPath();

    /**
     * @param string $path
     * @return self
     */
    public function setPath($path);

    /**
     * @return array
     */
    public function getMetaData();

    /**
     * @param array $metaData
     * @return self
     */
    public function setMetaData($metaData);

    /**
     * @return DataSourceInterface
     */
    public function getDataSource();

    /**
     * @param DataSourceInterface $dataSource
     * @return self
     */
    public function setDataSource(DataSourceInterface $dataSource);

    /**
     * @return mixed
     */
    public function getReceivedVia();

    /**
     * @param mixed $receivedVia
     * @return self
     */
    public function setReceivedVia($receivedVia);

    /**
     * @param $receivedVia
     * @return bool
     */
    public static function isSupportedReceivedViaType($receivedVia);

    /**
     * @return mixed
     */
    public function getFileName();

    /**
     * @param mixed $fileName
     * @return self
     */
    public function setFileName($fileName);

    /**
     * @return mixed
     */
    public function getHashFile();

    /**
     * @param mixed $hashFile
     * @return self
     */
    public function setHashFile($hashFile);


    /**
     * @return mixed
     */
    public function getTotalRow();

    /**
     * @param mixed $totalRow
     * @return self
     */
    public function setTotalRow($totalRow);

    /**
     * @return string
     */
    public function getFileExtension();

    /**
     * @param string $fileExtension
     * @return self
     */
    public function setFileExtension($fileExtension);

    /**
     * @return mixed
     * @return self
     */
    public function getImportHistories();

    /**
     * @param mixed $importHistories
     */
    public function setImportHistories($importHistories);

    /**
     * @return array
     */
    public function getMissingDate();

    /**
     * @param array $missingDate
     * @return self
     */
    public function setMissingDate($missingDate);

    /**
     * @return DateTime
     */
    public function getStartDate();

    /**
     * @param DateTime $startDate
     * @return self
     */
    public function setStartDate($startDate);

    /**
     * @return DateTime
     */
    public function getEndDate();

    /**
     * @param DateTime $endDate
     * @return self
     */
    public function setEndDate($endDate);

    /**
     * @return boolean
     */
    public function isDateRangeBroken();

    /**
     * @param boolean $dateRangeBroken
     * @return self
     */
    public function setDateRangeBroken($dateRangeBroken);

    /**
     * @return array
     */
    public function getDates();

    /**
     * @param array $dates
     * @return self
     */
    public function setDates($dates);

    /**
     * @return mixed
     */
    public function getRemoveHistory();

    /**
     * @param mixed $removeHistory
     * @return self
     */
    public function setRemoveHistory($removeHistory);

    /**
     * @return boolean
     */
    public function isSeparable(): bool;

    /**
     * @param boolean $separable
     * @return self
     */
    public function setSeparable($separable);

    /**
     * @return array
     */
    public function getChunks(): array;

    /**
     * @param array $chunks
     * @return self
     */
    public function setChunks($chunks);
}