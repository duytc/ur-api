<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSourceIntegrationInterface extends ModelInterface
{
    /**
     * @return DataSourceInterface
     */
    public function getDataSource();

    /**
     * @param DataSourceInterface $dataSource
     * @return self
     */

    public function setDataSource(DataSourceInterface $dataSource);

    /**
     * @return IntegrationInterface
     */

    public function getIntegration();

    /**
     * @param IntegrationInterface $integration
     * @return self
     */
    public function setIntegration(IntegrationInterface $integration);

    /**
     * @return string
     */
    public function getParams();

    /**
     * @param array $params
     * @return self
     */
    public function setParams(array $params);

    /**
     * @return int
     */
    public function getSchedule();

    /**
     * @param int $schedule
     * @return self
     */
    public function setSchedule($schedule);

    /**
     * @return boolean
     */
    public function getActive();

    /**
     * @param boolean $active
     * @return self
     */
    public function setActive($active);

    /**
     * @return mixed
     */
    public function getLastExecutedAt();

    /**
     * @param mixed $lastExecutedAt
     */
    public function setLastExecutedAt($lastExecutedAt);
}