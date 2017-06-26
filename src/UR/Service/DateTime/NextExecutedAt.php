<?php

namespace UR\Service\DateTime;

use Doctrine\ORM\EntityManagerInterface;
use UR\Entity\Core\DataSourceIntegrationSchedule;
use UR\Model\Core\DataSourceIntegration;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;

class NextExecutedAt
{
    /** @var DateTimeUtil  */
    private $dateTimeUtil;

    /** @var  EntityManagerInterface */
    private $em;

    /**
     * DateTimeUtil constructor.
     * @param DateTimeUtil $dateTimeUtil
     */
    public function __construct(DateTimeUtil $dateTimeUtil)
    {
        $this->dateTimeUtil = $dateTimeUtil;
    }

    /**
     * @param DataSourceIntegrationInterface $dataSourceIntegration
     * @param EntityManagerInterface $em
     * @return DataSourceIntegrationInterface
     */
    public function updateDataSourceIntegrationSchedule(DataSourceIntegrationInterface $dataSourceIntegration, EntityManagerInterface $em)
    {
        $this->em = $em;
        // update uuid to schedule setting, TODO: move to new listener
        $scheduleSetting = $dataSourceIntegration->getSchedule();
        $scheduleSetting = $this->organizeScheduleSetting($scheduleSetting);

        // update to dataSourceIntegration
        $dataSourceIntegration->setSchedule($scheduleSetting);
        $lastExecuted = $this->getLastExecutedFromDataSourceIntegration($dataSourceIntegration);

        $dateInterval = new \DateInterval(sprintf('PT%dH', 48)); // e.g PT2H = period time 24 hours
        $lastExecuted->sub($dateInterval);

        $checkType = $scheduleSetting[DataSourceIntegration::SCHEDULE_KEY_CHECKED];
        $checkValue = $scheduleSetting[$checkType];

        switch ($checkType) {
            case DataSourceIntegration::SCHEDULE_CHECKED_CHECK_EVERY:
                $nextExecuteAt = $this->dateTimeUtil->getNextExecutedByCheckEvery($lastExecuted, $checkValue);
                $newDataSourceIntegrationSchedules = [
                    (new DataSourceIntegrationSchedule())
                        ->setUuid($checkValue[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_UUID])
                        ->setExecutedAt($nextExecuteAt)
                        ->setPending(false)
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
                        ->setExecutedAt($nextExecuteAt)
                        ->setPending(false)
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

    /**
     * @param DataSourceIntegrationInterface $dataSourceIntegration
     * @return \DateTime
     */
    private function getLastExecutedFromDataSourceIntegration(DataSourceIntegrationInterface $dataSourceIntegration)
    {
        /** Avoid error when create data source with integration */
        if ($dataSourceIntegration->getId() == null) {
            return date_create('yesterday');
        }

        $dataSource = $dataSourceIntegration->getDataSource();
        $dataSourceIntegrationScheduleRepository = $this->em->getRepository(DataSourceIntegrationSchedule::class);
        $oldSchedules = $dataSourceIntegrationScheduleRepository->findByDataSource($dataSource);

        foreach ($oldSchedules as $schedule) {
            if (!$schedule instanceof DataSourceIntegrationScheduleInterface) {
                continue;
            }

            if ($schedule->getExecutedAt() instanceof \DateTime) {
                return $schedule->getExecutedAt();
            }
        }

        return date_create();
    }
}