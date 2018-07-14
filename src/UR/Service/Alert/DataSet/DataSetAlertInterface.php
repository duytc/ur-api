<?php

namespace UR\Service\Alert\DataSet;

use UR\Model\Core\DataSetInterface;
use UR\Service\Alert\AlertDTOInterface;

interface DataSetAlertInterface extends AlertDTOInterface
{
    const ALERT_TYPE_KEY = 'type';

    /* values for alert type */
    const ALERT_TYPE_VALUE_DATA_AUGMENTED_DATA_SET_CHANGED = 'dataAugmentedDataSetChanged';

    const ALERT_TIME_ZONE_KEY = 'alertTimeZone';
    const ALERT_HOUR_KEY = 'alertHour';
    const ALERT_MINUTE_KEY = 'alertMinutes';

    const ALERT_ACTIVE_KEY = 'active';
    const ALERT_DETAIL_KEY = 'detail';
    const DATE = 'date';

    const MESSAGE_NOTICE_PUBLISHER = 'message';

    /**
     * @return mixed
     */
    public function getDetails();

    /**
     * @return mixed
     */
    public function getAlertCode();

    /**
     * @return null|DataSetInterface
     */
    public function getDataSet();

    /**
     * @return null|int
     */
    public function getDataSetId();
}