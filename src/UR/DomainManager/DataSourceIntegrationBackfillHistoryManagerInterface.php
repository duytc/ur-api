<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;

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
}