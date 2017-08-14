<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSourceIntegrationScheduleInterface extends ModelInterface
{
    const FIELD_NEXT_EXECUTED_AT = 'nextExecutedAt';
    const FIELD_FINISHED_AT = 'finishedAt';
    const FIELD_STATUS = 'status';

    const FETCHER_STATUS_NOT_RUN = 0;
    const FETCHER_STATUS_PENDING = 1;
    const FETCHER_STATUS_FINISHED = 2;

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
    public function getNextExecutedAt();

    /**
     * @param \DateTime $nextExecutedAt
     * @return self
     */
    public function setNextExecutedAt(\DateTime $nextExecutedAt);

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
     * @return integer
     */
    public function getStatus();

    /**
     * @param integer $status
     * @return self
     */
    public function setStatus($status);

    /**
     * @return \DateTime
     */
    public function getQueuedAt();

    /**
     * @param \DateTime $queuedAt
     * @return self
     */
    public function setQueuedAt($queuedAt);

    /**
     * @return \DateTime
     */
    public function getFinishedAt();

    /**
     * @param \DateTime $finishedAt
     * @return self
     */
    public function setFinishedAt($finishedAt);
}