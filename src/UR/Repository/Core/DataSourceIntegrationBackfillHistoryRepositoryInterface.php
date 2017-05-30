<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;
use UR\Model\Core\DataSourceInterface;

interface DataSourceIntegrationBackfillHistoryRepositoryInterface extends ObjectRepository
{

    /**
     * @param integer $dataSourceIntegrationId
     * @return array|DataSourceIntegrationBackfillHistoryInterface[]
     */
    public function findByDataSourceIntegration($dataSourceIntegrationId);

    /**
     * @param DataSourceInterface $dataSource
     * @return array
     */
    public function getBackfillHistoriesByDataSourceId(DataSourceInterface $dataSource);
}