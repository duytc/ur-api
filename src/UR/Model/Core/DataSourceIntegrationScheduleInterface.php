<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface DataSourceIntegrationScheduleInterface extends ModelInterface
{
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
}