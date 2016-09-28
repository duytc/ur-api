<?php

namespace Tagcade\Model\Core;

use Tagcade\Model\ModelInterface;
use Tagcade\Model\User\Role\PublisherInterface;

interface DataSourceIntegrationInterface extends ModelInterface
{
    /**
     * @return DataSourceInterface
     */
    public function getDataSource();
    /**
     * @param DataSourceInterface $dataSource
     */
    public function setDataSource($dataSource);
    /**
     * @return IntegrationInterface
     */
    public function getIntegration();

    /**
     * @param IntegrationInterface $integration
     */
    public function setIntegration($integration);

    /**
     * @return string
     */
    public function getUsername();

    /**
     * @param string $username
     */
    public function setUsername($username);

    /**
     * @return string
     */
    public function getPassword();
    /**
     * @param string $password
     */
    public function setPassword($password);

    /**
     * @return int
     */
    public function getSchedule();

    /**
     * @param int $schedule
     */
    public function setSchedule($schedule);
}