<?php

namespace UR\Model\Core;

use Doctrine\Common\Collections\Collection;

class DataSourceIntegration implements DataSourceIntegrationInterface
{
    const SCHEDULE_KEY_CHECKED = 'checked';
    const SCHEDULE_KEY_CHECK_EVERY = 'checkEvery';
    const SCHEDULE_KEY_CHECK_AT = 'checkAt';

    const SCHEDULE_KEY_CHECK_AT_KEY_TIME_ZONE = 'timeZone';
    const SCHEDULE_KEY_CHECK_AT_KEY_HOUR = 'hour';
    const SCHEDULE_KEY_CHECK_AT_KEY_MINUTES = 'minutes';
    const SCHEDULE_KEY_CHECK_AT_KEY_UUID = 'uuid';

    const SCHEDULE_CHECKED_CHECK_EVERY = 'checkEvery';
    const SCHEDULE_CHECKED_CHECK_AT = 'checkAt';

    public static $SUPPORTED_SCHEDULE_CHECK_AT_KEYS = [
        self::SCHEDULE_KEY_CHECK_AT_KEY_TIME_ZONE,
        self::SCHEDULE_KEY_CHECK_AT_KEY_HOUR,
        self::SCHEDULE_KEY_CHECK_AT_KEY_MINUTES,
        self::SCHEDULE_KEY_CHECK_AT_KEY_UUID

    ];

    public static $SUPPORTED_SCHEDULE_CHECKED_TYPES = [
        self::SCHEDULE_CHECKED_CHECK_EVERY,
        self::SCHEDULE_CHECKED_CHECK_AT
    ];

    protected $id;

    /** @var array */
    protected $params;
    protected $schedule; // json_string
    protected $active;
    protected $lastExecutedAt;

    // back fill feature
    /** @var bool */
    protected $backFill;
    /** @var \DateTime|null */
    protected $backFillStartDate;
    /** @var bool */
    protected $backFillExecuted;
    /** @var bool */
    protected $backFillForce;

    /** @var DataSourceInterface */
    protected $dataSource;

    /** @var IntegrationInterface */
    protected $integration;

    /** @var DataSourceIntegrationScheduleInterface[] */
    protected $dataSourceIntegrationSchedules;

    public function __construct()
    {
        $this->active = true;

        // back fill feature
        $this->backFill = false;
        $this->backFillStartDate = null;
        $this->backFillExecuted = false;
        $this->backFillForce = false;
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
    public function getIntegration()
    {
        return $this->integration;
    }

    /**
     * @inheritdoc
     */
    public function setIntegration(IntegrationInterface $integration)
    {
        $this->integration = $integration;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getParams()
    {
        if (!is_array($this->params)) {
            return $this->params;
        }

        $noneSecureParams = [];

        foreach ($this->params as $param) {
            if (!is_array($param) || (!array_key_exists(Integration::PARAM_KEY_VALUE, $param) && !array_key_exists(Integration::PARAM_KEY_TYPE, $param))) {
                continue;
            }

            // add original param if is not secure
            if ($param[Integration::PARAM_KEY_TYPE] !== Integration::PARAM_TYPE_SECURE) {
                $noneSecureParams[] = $param;
                continue;
            }

            // clear secure value in original param before add to result
            $param[Integration::PARAM_KEY_VALUE] = null;
            $noneSecureParams[] = $param;
        }

        return $noneSecureParams;
    }

    /**
     * @inheritdoc
     */
    public function getOriginalParams()
    {
        return $this->params;

    }

    /**
     * @inheritdoc
     */
    public function setParams(array $params)
    {
        $this->params = $params;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function encryptSecureParams()
    {
        if (!is_array($this->params)) {
            return;
        }

        foreach ($this->params as &$param) {
            if (!is_array($param) || (!array_key_exists(Integration::PARAM_KEY_VALUE, $param) && !array_key_exists(Integration::PARAM_KEY_TYPE, $param))) {
                continue;
            }

            if ($param[Integration::PARAM_KEY_TYPE] !== Integration::PARAM_TYPE_SECURE) {
                continue;
            }

            $value = $param[Integration::PARAM_KEY_VALUE];
            //if ($this->is_base64_encoded($value)) {
            //    continue;
            //}

            $param[Integration::PARAM_KEY_VALUE] = base64_encode($value);
        }
    }

    /**
     * @inheritdoc
     */
    public function decryptSecureParam($paramValue)
    {
        if (!$this->is_base64_encoded($paramValue)) {
            return $paramValue;
        }

        $decodedParamValue = base64_decode($paramValue);

        return (false == $decodedParamValue) ? $paramValue : $decodedParamValue;
    }

    /**
     * @inheritdoc
     */
    public function getSchedule()
    {
        return $this->schedule;
    }

    /**
     * @inheritdoc
     */
    public function setSchedule($schedule)
    {
        $this->schedule = $schedule;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getActive()
    {
        return $this->active;
    }

    /**
     * @inheritdoc
     */
    public function setActive($active)
    {
        $this->active = $active;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getLastExecutedAt()
    {
        return $this->lastExecutedAt;
    }

    /**
     * @inheritdoc
     */
    public function setLastExecutedAt($lastExecutedAt)
    {
        $this->lastExecutedAt = $lastExecutedAt;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isBackFill()
    {
        return $this->backFill;
    }

    /**
     * @inheritdoc
     */
    public function setBackFill($backFill)
    {
        $this->backFill = $backFill;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getBackFillStartDate()
    {
        return $this->backFillStartDate;
    }

    /**
     * @inheritdoc
     */
    public function setBackFillStartDate($backFillStartDate)
    {
        $this->backFillStartDate = $backFillStartDate;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isBackFillExecuted()
    {
        return $this->backFillExecuted;
    }

    /**
     * @inheritdoc
     */
    public function setBackFillExecuted($backFillExecuted)
    {
        $this->backFillExecuted = $backFillExecuted;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function isBackFillForce()
    {
        return $this->backFillForce;
    }

    /**
     * @inheritdoc
     */
    public function setBackFillForce($backFillForce)
    {
        $this->backFillForce = $backFillForce;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getDataSourceIntegrationSchedules()
    {
        return $this->dataSourceIntegrationSchedules;
    }

    /**
     * @inheritdoc
     */
    public function setDataSourceIntegrationSchedules(array $dataSourceIntegrationSchedules)
    {
        $this->dataSourceIntegrationSchedules = $dataSourceIntegrationSchedules;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function removeAllDataSourceIntegrationSchedules()
    {
        if ((!is_array($this->dataSourceIntegrationSchedules) && !$this->dataSourceIntegrationSchedules instanceof Collection)
            || count($this->dataSourceIntegrationSchedules) < 1
        ) {
            return;
        }

        foreach ($this->dataSourceIntegrationSchedules as $k => $v) {
            unset($this->dataSourceIntegrationSchedules[$k]);
        }

        unset($this->dataSourceIntegrationSchedules);
    }

    /**
     * check if is base64 encoded
     * @param $data
     * @return bool
     */
    private function is_base64_encoded($data)
    {
        if (!is_string($data)) {
            return false;
        }

        $decoded = base64_decode($data, true);

        // Check if there is no invalid character in string
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $data)) return false;

        // Decode the string in strict mode and send the response
        if (!base64_decode($data, true)) return false;

        // Encode and compare it to original one
        if (base64_encode($decoded) != $data) return false;

        return true;
    }
}