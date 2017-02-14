<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSourceEntryInterface extends ModelInterface
{
    const RECEIVED_VIA_UPLOAD = "upload";
    const RECEIVED_VIA_SELENIUM = "selenium";
    const RECEIVED_VIA_API = "api";
    const RECEIVED_VIA_EMAIL = "email";
    const META_DATA_EMAIL_SUBJECT = "subject";
    const META_DATA_EMAIL_FROM = "from";
    const META_DATA_EMAIL_BODY = "body";
    const META_DATA_EMAIL_DATE = "date";
    const META_DATA_FILE_NAME = "filename";

    /**
     * @return mixed
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
}