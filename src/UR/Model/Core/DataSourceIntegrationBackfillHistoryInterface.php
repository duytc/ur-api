<?php

namespace UR\Model\Core;

use Doctrine\Common\Collections\Collection;
use UR\Model\ModelInterface;
use UR\Model\Core\DataSourceIntegrationInterface;

interface DataSourceIntegrationBackfillHistoryInterface extends ModelInterface
{

    /**
     * @return DataSourceIntegrationInterface
     */

    public function getDataSourceIntegration();

    /**
     * @param DatasourceIntegrationInterface $datasourceintegration
     * @return self
     */
    public function setDataSourceIntegration(DataSourceIntegrationInterface $datasourceintegration);

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

}