<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSourceIntegrationScheduleInterface extends ModelInterface
{
    const FIELD_EXECUTED_AT = 'executedAt';
    const FIELD_PENDING = 'pending';
    
    /**
     * @return mixed
     */
    public function getUuid();

    /**
     * @param mixed $uuid
     * @return self
     */
    public function setUuid($uuid);

    /**
     * @return \DateTime
     */
    public function getExecutedAt();

    /**
     * @param \DateTime $executedAt
     * @return self
     */
    public function setExecutedAt(\DateTime $executedAt);

    /**
     * @return string
     */
    public function getScheduleType();

    /**
     * @param string $scheduleType
     * @return self
     */
    public function setScheduleType($scheduleType);

    /**
     * @return DataSourceIntegrationInterface
     */
    public function getDataSourceIntegration();

    /**
     * @param DataSourceIntegrationInterface $dataSourceIntegration
     * @return self
     */
    public function setDataSourceIntegration(DataSourceIntegrationInterface $dataSourceIntegration);

    /**
     * @return bool
     */
    public function getPending();

    /**
     * @param bool $pending
     * @return self
     */
    public function setPending($pending);
}