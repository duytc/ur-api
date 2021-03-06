<?php

namespace UR\Model\Core;

use Doctrine\Common\Collections\Collection;
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
     * notice: we return params without secure value in params.
     * This is used for REST API for create/edit without showing up real secure value
     * e.g: {key => password, type => secure, value => DMN342KJS== } will return {key => password, type => secure, value => null }
     *
     * @return string
     */
    public function getParams();

    /**
     * get original Params
     * e.g: {key => password, type => secure, value => DMN342KJS== } will return {key => password, type => secure, value => DMN342KJS== }
     *
     * @return array
     */
    public function getOriginalParams();

    /**
     * @param array $params
     * @return self
     */
    public function setParams(array $params);

    /**
     * @return mixed
     */
    public function encryptSecureParams();

    /**
     * @param $paramValue
     * @return mixed
     */
    public function decryptSecureParam($paramValue);

    /**
     * @return array
     */
    public function getSchedule();

    /**
     * @param array $schedule
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
     * @return array|Collection|DataSourceIntegrationScheduleInterface[]
     */
    public function getDataSourceIntegrationSchedules();

    /**
     * @param DataSourceIntegrationScheduleInterface[] $dataSourceIntegrationSchedules
     * @return self
     */
    public function setDataSourceIntegrationSchedules(array $dataSourceIntegrationSchedules);

    /**
     * @return mixed
     */
    public function removeAllDataSourceIntegrationSchedules();
}