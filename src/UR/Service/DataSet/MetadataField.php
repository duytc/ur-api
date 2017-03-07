<?php

namespace UR\Service\DataSet;

final class MetadataField
{
    const FILE_NAME = '[__filename]';
    const EMAIL_SUBJECT = '[__email_subject]';
    const EMAIL_BODY = '[__email_body]';
    const EMAIL_DATE_TIME = '[__email_date_time]';

    public static $internalFields = [
        self::FILE_NAME,
        self::EMAIL_SUBJECT,
        self::EMAIL_BODY,
        self::EMAIL_DATE_TIME
    ];
}