<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSourceIntegrationBackfillHistoryInterface extends ModelInterface
{
    const FIELD_QUEUED_AT = 'queuedAt';
    const FIELD_FINISHED_AT = 'finishedAt';
    const FIELD_STATUS = 'status';

    const FETCHER_STATUS_NOT_RUN = 0;
    const FETCHER_STATUS_PENDING = 1;
    const FETCHER_STATUS_FINISHED = 2;
    const FETCHER_STATUS_FAILED = 3;

    /**
     * @return DataSourceIntegrationInterface
     */

    public function getDataSourceIntegration();

    /**
     * @param DatasourceIntegrationInterface $dataSourceIntegration
     * @return self
     */
    public function setDataSourceIntegration(DataSourceIntegrationInterface $dataSourceIntegration);

    /**
     * @return mixed
     */
    public function getQueuedAt();

    /**
     * @param mixed $queuedAt
     */
    public function setQueuedAt($queuedAt);

    /**
     * @return \DateTime|null
     */
    public function getBackFillStartDate();

    /**
     * @param \DateTime|null $backFillStartDate
     * @return self
     */
    public function setBackFillStartDate($backFillStartDate);

    /**
     * @return \DateTime|null
     */
    public function getBackFillEndDate();

    /**
     * @param \DateTime|null $backFillEndDate
     * @return self
     */
    public function setBackFillEndDate($backFillEndDate);

    /**
     * @return mixed
     */
    public function getStatus();

    /**
     * @param mixed $status
     * @return self
     */
    public function setStatus($status);

    /**
     * @return \DateTime
     */
    public function getFinishedAt();

    /**
     * @param \DateTime $finishedAt
     * @return self
     */
    public function setFinishedAt($finishedAt);

    /**
     * @return boolean
     */
    public function getAutoCreate();

    /**
     * @param mixed $autoCreate
     * @return self
     */
    public function setAutoCreate($autoCreate);
}