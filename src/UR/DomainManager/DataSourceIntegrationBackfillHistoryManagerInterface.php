<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;
use UR\Model\Core\DataSourceInterface;

interface DataSourceIntegrationBackfillHistoryManagerInterface extends ManagerInterface
{
    /**
     * @param integer $dataSourceIntegrationId
     * @return array|DataSourceIntegrationBackfillHistoryInterface[]
     */
    public function findByDataSourceIntegration($dataSourceIntegrationId);

    /**
     * @return array|DataSourceIntegrationBackfillHistoryInterface[]
     */
    public function findByBackFillNotExecuted();

    /**
     * @param DataSourceInterface $dataSource
     * @return mixed
     */
    public function deleteCurrentAutoCreateBackFillHistory(DataSourceInterface $dataSource);
}