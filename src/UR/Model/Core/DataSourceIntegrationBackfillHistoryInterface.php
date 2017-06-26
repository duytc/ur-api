<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSourceIntegrationBackfillHistoryInterface extends ModelInterface
{
    const FIELD_EXECUTED_AT = 'executedAt';
    const FIELD_PENDING = 'pending';

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
    public function getExecutedAt();

    /**
     * @param mixed $executedAt
     */
    public function setExecutedAt($executedAt);

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
    public function getPending();

    /**
     * @param mixed $status
     * @return self
     */
    public function setPending($status);
}