<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSourceIntegrationBackfillHistoryInterface extends ModelInterface
{
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
    public function getLastExecutedAt();

    /**
     * @param mixed $lastExecutedAt
     */
    public function setLastExecutedAt($lastExecutedAt);

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
    public function getIsRunning();

    /**
     * @param mixed $status
     * @return self
     */
    public function setIsRunning($status);
}