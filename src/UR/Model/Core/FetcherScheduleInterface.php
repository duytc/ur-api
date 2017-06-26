<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface FetcherScheduleInterface extends ModelInterface
{
    /**
     * @return DataSourceIntegrationBackfillHistoryInterface
     */
    public function getBackFillHistory();

    /**
     * @param DataSourceIntegrationBackfillHistoryInterface $backFillHistory
     * @return self
     */
    public function setBackFillHistory($backFillHistory);

    /**
     * @return DataSourceIntegrationScheduleInterface
     */
    public function getDataSourceIntegrationSchedule();

    /**
     * @param DataSourceIntegrationScheduleInterface $dataSourceIntegrationSchedule
     * @return self
     */
    public function setDataSourceIntegrationSchedule($dataSourceIntegrationSchedule);

    /**
     * @return mixed
     */
    public function getId();

    /**
     * @param mixed $id
     */
    public function setId($id);
}