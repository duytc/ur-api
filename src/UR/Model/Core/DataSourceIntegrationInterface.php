<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;
use UR\Model\User\Role\PublisherInterface;

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
    public function getUsername();

    /**
     * @param string $username
     * @return self
     */
    public function setUsername($username);

    /**
     * @return string
     */

    public function getPassword();
    /**
     * @param string $password
     * @return self
     */

    public function setPassword($password);

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
}