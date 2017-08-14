<?php

namespace UR\Service\DateTime;

use DateTimeZone;
use UR\Domain\DTO\Report\Transforms\GroupByTransform;
use UR\Entity\Core\DataSourceIntegrationSchedule;
use UR\Model\Core\DataSourceIntegration;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;

class NextExecutedAt
{
    /** @var DateTimeUtil */
    private $dateTimeUtil;

    /**
     * DateTimeUtil constructor.
     * @param DateTimeUtil $dateTimeUtil
     */
    public function __construct(DateTimeUtil $dateTimeUtil)
    {
        $this->dateTimeUtil = $dateTimeUtil;
    }

    /**
     * @param DataSourceIntegrationScheduleInterface $dataSourceIntegrationSchedule
     * @return DataSourceIntegrationScheduleInterface
     */
    public function updateDataSourceIntegrationSchedule(DataSourceIntegrationScheduleInterface $dataSourceIntegrationSchedule)
    {
        $dataSourceIntegration = $dataSourceIntegrationSchedule->getDataSourceIntegration();
        $scheduleSetting = $dataSourceIntegration->getSchedule();
        $lastExecuted = date_create('now', new DateTimeZone(GroupByTransform::DEFAULT_TIMEZONE));

        $checkType = $scheduleSetting[DataSourceIntegration::SCHEDULE_KEY_CHECKED];
        $checkValue = $scheduleSetting[$checkType];

        switch ($checkType) {
            case DataSourceIntegration::SCHEDULE_CHECKED_CHECK_EVERY:
                $nextExecuteAt = $this->dateTimeUtil->getNextExecutedByCheckEvery($lastExecuted, $checkValue);

                $dataSourceIntegrationSchedule
                    ->setNextExecutedAt($nextExecuteAt);

                break;

            case DataSourceIntegration::SCHEDULE_CHECKED_CHECK_AT:
                foreach ($checkValue as $checkAtItem) {
                    if (!array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_UUID, $checkAtItem)
                        || $dataSourceIntegrationSchedule->getUuid() !== $checkAtItem[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_UUID]
                    ) {
                        continue;
                    }

                    $nextExecuteAt = $this->dateTimeUtil->getNextExecutedByCheckAt($lastExecuted, $checkAtItem);

                    $dataSourceIntegrationSchedule
                        ->setNextExecutedAt($nextExecuteAt);
                }

                break;
        }

        return $dataSourceIntegrationSchedule;
    }

    /**
     * @param DataSourceIntegrationInterface $dataSourceIntegration
     * @return DataSourceIntegrationInterface
     */
    public function updateDataSourceIntegrationScheduleForDataSourceIntegration(DataSourceIntegrationInterface $dataSourceIntegration)
    {
        // update uuid to schedule setting, TODO: move to new listener
        $scheduleSetting = $dataSourceIntegration->getSchedule();
        $scheduleSetting = $this->organizeScheduleSetting($scheduleSetting);

        // update to dataSourceIntegration
        $dataSourceIntegration->setSchedule($scheduleSetting);
        $lastExecuted = date_create('now', new DateTimeZone(GroupByTransform::DEFAULT_TIMEZONE));

        $checkType = $scheduleSetting[DataSourceIntegration::SCHEDULE_KEY_CHECKED];
        $checkValue = $scheduleSetting[$checkType];

        switch ($checkType) {
            case DataSourceIntegration::SCHEDULE_CHECKED_CHECK_EVERY:
                $nextExecuteAt = $this->dateTimeUtil->getNextExecutedByCheckEvery($lastExecuted, $checkValue);
                $newDataSourceIntegrationSchedules = [
                    (new DataSourceIntegrationSchedule())
                        ->setUuid($checkValue[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_UUID])
                        ->setNextExecutedAt($nextExecuteAt)
                        ->setStatus(DataSourceIntegrationScheduleInterface::FETCHER_STATUS_NOT_RUN)
                        ->setScheduleType($checkType)
                        ->setDataSourceIntegration($dataSourceIntegration)
                ];

                break;

            case DataSourceIntegration::SCHEDULE_CHECKED_CHECK_AT:
                $newDataSourceIntegrationSchedules = [];

                foreach ($checkValue as $checkAtItem) {
                    $nextExecuteAt = $this->dateTimeUtil->getNextExecutedByCheckAt($lastExecuted, $checkAtItem);
                    $newDataSourceIntegrationSchedules[] = ((new DataSourceIntegrationSchedule())
                        ->setUuid($checkAtItem[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_UUID])
                        ->setNextExecutedAt($nextExecuteAt)
                        ->setStatus(DataSourceIntegrationScheduleInterface::FETCHER_STATUS_NOT_RUN)
                        ->setScheduleType($checkType)
                        ->setDataSourceIntegration($dataSourceIntegration));
                }

                break;

            default:
                // not supported
                return $dataSourceIntegration;
        }

        // update, we already have setting cascade persist and orphanRemoval
        // so that $newDataSourceIntegrationSchedules will be applied for the $dataSourceIntegration
        $dataSourceIntegration->removeAllDataSourceIntegrationSchedules();
        $dataSourceIntegration->setDataSourceIntegrationSchedules($newDataSourceIntegrationSchedules);

        return $dataSourceIntegration;
    }

    /**
     * @param array $scheduleSetting
     * @return array
     */
    private function organizeScheduleSetting(array $scheduleSetting)
    {
        // add Uuid To ScheduleSetting
        if (array_key_exists(DataSourceIntegration::SCHEDULE_CHECKED_CHECK_EVERY, $scheduleSetting)) {
            $scheduleCheckEvery = $scheduleSetting[DataSourceIntegration::SCHEDULE_CHECKED_CHECK_EVERY];
            if (!array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_UUID, $scheduleCheckEvery)) {
                $scheduleCheckEvery[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_UUID] = $this->getUUid();
            }
            $scheduleSetting[DataSourceIntegration::SCHEDULE_CHECKED_CHECK_EVERY] = $scheduleCheckEvery;
        }

        if (array_key_exists(DataSourceIntegration::SCHEDULE_CHECKED_CHECK_AT, $scheduleSetting)) {
            $scheduleCheckAt = $scheduleSetting[DataSourceIntegration::SCHEDULE_CHECKED_CHECK_AT];
            foreach ($scheduleCheckAt as &$checkAt) {
                if (!array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_UUID, $checkAt)) {
                    $checkAt[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_UUID] = $this->getUUid();
                }
            }
            $scheduleSetting[DataSourceIntegration::SCHEDULE_CHECKED_CHECK_AT] = $scheduleCheckAt;
        }

        return $scheduleSetting;
    }

    /**
     * @return string
     */
    private function getUUid()
    {
        return bin2hex(random_bytes(18));
    }
}