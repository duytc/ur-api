<?php

namespace UR\Model\Core;


use UR\Service\DataSet\MetadataField;

class DataSourceEntryMetadata
{
    const META_DATA_EMAIL_SUBJECT = "subject";
    const META_DATA_EMAIL_FROM = "from";
    const META_DATA_EMAIL_BODY = "body";
    const META_DATA_EMAIL_DATE = "dateTime";
    const META_DATA_FILE_NAME = "filename";

    static $MAPPED_META_DATA_EMAIL_HIDDEN_COLUMNS = [
        self::META_DATA_EMAIL_BODY => MetadataField::EMAIL_BODY,
        self::META_DATA_EMAIL_DATE => MetadataField::EMAIL_DATE_TIME,
        //self::META_DATA_EMAIL_FROM => '', // not yet used
        self::META_DATA_EMAIL_SUBJECT => MetadataField::EMAIL_SUBJECT,
        self::META_DATA_FILE_NAME => MetadataField::FILE_NAME
    ];

    protected $fileName;
    protected $emailBody;
    protected $emailSubject;
    protected $emailDatetime;
    private $metadata;

    /**
     * DataSourceEntryMetadata constructor.
     * @param array $metadata
     */
    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
    }

    /**
     * @return mixed
     */
    public function getFileName()
    {
        if (!array_key_exists(self::META_DATA_FILE_NAME, $this->metadata)) {
            return null;
        }

        return $this->metadata[self::META_DATA_FILE_NAME];
    }

    /**
     * @param mixed $fileName
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
    }

    /**
     * @return mixed
     */
    public function getEmailBody()
    {
        if (!array_key_exists(self::META_DATA_EMAIL_BODY, $this->metadata)) {
            return null;
        }

        return $this->metadata[self::META_DATA_EMAIL_BODY];
    }

    /**
     * @param mixed $emailBody
     */
    public function setEmailBody($emailBody)
    {
        $this->emailBody = $emailBody;
    }

    /**
     * @return mixed
     */
    public function getEmailSubject()
    {
        if (!array_key_exists(self::META_DATA_EMAIL_SUBJECT, $this->metadata)) {
            return null;
        }

        return $this->metadata[self::META_DATA_EMAIL_SUBJECT];
    }

    /**
     * @param mixed $emailSubject
     */
    public function setEmailSubject($emailSubject)
    {
        $this->emailSubject = $emailSubject;
    }

    /**
     * @return mixed
     */
    public function getEmailDatetime()
    {
        if (!array_key_exists(self::META_DATA_EMAIL_DATE, $this->metadata)) {
            return null;
        }

        $this->emailDatetime = $this->metadata[self::META_DATA_EMAIL_DATE];
        return $this->emailDatetime;
    }

    /**
     * @param mixed $emailDatetime
     */
    public function setEmailDatetime($emailDatetime)
    {
        $this->emailDatetime = $emailDatetime;
    }

    /**
     * get Mapped Hidden Columns without brackets
     *
     * @return array
     */
    public function getMappedHiddenColumnsWithoutBrackets()
    {
        $mappedHiddenColumns = [];

        foreach ($this->metadata as $key => $value) {
            if (!array_key_exists($key, self::$MAPPED_META_DATA_EMAIL_HIDDEN_COLUMNS)) {
                continue;
            }

            $columnWithoutBrackets = self::$MAPPED_META_DATA_EMAIL_HIDDEN_COLUMNS[$key];
            $columnWithoutBrackets = str_replace('[', '', $columnWithoutBrackets);
            $columnWithoutBrackets = str_replace(']', '', $columnWithoutBrackets);
            $mappedHiddenColumns[$key] = $columnWithoutBrackets;
        }

        return $mappedHiddenColumns;
    }
}