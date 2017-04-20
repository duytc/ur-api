<?php

namespace UR\Service\Metadata\Email;

use UR\Service\Metadata\MetadataInterface;

class EmailMetadata implements MetadataInterface
{
    const META_DATA_EMAIL_SUBJECT = 'subject';
    const META_DATA_EMAIL_FROM = 'from';
    const META_DATA_EMAIL_BODY = 'body';
    const META_DATA_EMAIL_DATE = 'dateTime';
    const INTEGRATION_META_DATA_REPORT_DATE = 'date';

    const EMAIL_SUBJECT = '[__email_subject]';
    const EMAIL_BODY = '[__email_body]';
    const EMAIL_DATE_TIME = '[__email_date_time]';
    const INTEGRATION_REPORT_DATE = '[__date]';
    const INTEGRATION_REPORT_DATE_COLUMN_MAPPING = '__date';

    private $emailBody;
    private $emailSubject;
    private $emailDatetime;
    private $metadata;
    private $integrationReportDate;

    public static $internalFields = [
        self::FILE_NAME,
        self::EMAIL_SUBJECT,
        self::EMAIL_BODY,
        self::EMAIL_DATE_TIME,
        self::INTEGRATION_REPORT_DATE
    ];

    public function __construct(array $metadata)
    {
        $this->metadata = $metadata;
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
     * @param $internalVariable
     * @return mixed|null
     */
    public function getMetadataValueByInternalVariable($internalVariable)
    {
        switch ($internalVariable) {
            case self::EMAIL_SUBJECT:
                return $this->getEmailSubject();
            case self::EMAIL_BODY:
                return $this->getEmailBody();
            case self::EMAIL_DATE_TIME:
                return $this->getEmailDatetime();
            case self::INTEGRATION_REPORT_DATE:
                return $this->getIntegrationReportDate();
            default:
                return null;
        }
    }

    /**
     * @return mixed
     */
    public function getIntegrationReportDate()
    {
        if (!array_key_exists(self::INTEGRATION_META_DATA_REPORT_DATE, $this->metadata)) {
            return null;
        }

        $this->integrationReportDate = $this->metadata[self::INTEGRATION_META_DATA_REPORT_DATE];
        return $this->integrationReportDate;
    }
}