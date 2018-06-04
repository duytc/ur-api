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

    /**
     * @return array|DataSourceIntegrationBackfillHistoryInterface[]
     */
    public function findByBackFillNotExecuted();

    /**
     * @param $dataSourceIntegrationId
     * @return array|DataSourceIntegrationBackfillHistoryInterface[]
     */
    public function findByBackFillNotExecutedForDataSource($dataSourceIntegrationId);

    /**
     * @param $startDate
     * @param $endDate
     * @param $dataSourceIntegration
     * @return mixed
     */
    public function findHistoryByStartDateEndDate($startDate, $endDate, $dataSourceIntegration);

    /**
     * @param DataSourceInterface $dataSource
     * @return array
     */
    public function getBackfillHistoriesByDataSourceIdWithAutoCreated(DataSourceInterface $dataSource);

    /**
     * @param DataSourceInterface $dataSource
     * @return mixed
     */
    public function getCurrentAutoCreateBackFillHistory(DataSourceInterface $dataSource);
}