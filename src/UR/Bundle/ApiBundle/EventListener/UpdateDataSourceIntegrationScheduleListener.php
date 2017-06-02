<?php

namespace UR\Bundle\ApiBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Entity\Core\DataSourceIntegrationSchedule;
use UR\Model\Core\DataSourceIntegration;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Service\DateTime\DateTimeUtil;

class UpdateDataSourceIntegrationScheduleListener
{
    /** @var array|DataSourceIntegrationInterface[] */
    private $updateDataSourceIntegrations = [];

    /** @var  DateTimeUtil */
    private $dateTimeUtil;

    public function __construct(DateTimeUtil $dateTimeUtil)
    {
        $this->dateTimeUtil = $dateTimeUtil;
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function prePersist(LifecycleEventArgs $args)
    {
        $dataSourceIntegration = $args->getEntity();

        if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
            return;
        }

        // update all dataSourceIntegrationSchedules
        $dataSourceIntegration = $this->updateDataSourceIntegrationSchedule($dataSourceIntegration);

        // add to $updateDataSourceIntegrations
        $this->updateDataSourceIntegrations[] = $dataSourceIntegration;
    }

    /**
     * @param PreUpdateEventArgs $args
     */
    public function preUpdate(PreUpdateEventArgs $args)
    {
        // only do encrypt if params changed
        if ($args->hasChangedField('schedule') || $args->hasChangedField('lastExecutedAt')) {
            // Continue
        } else {
            return;
        }

        $dataSourceIntegration = $args->getEntity();
        if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
            return;
        }

        // update all dataSourceIntegrationSchedules
        $dataSourceIntegration = $this->updateDataSourceIntegrationSchedule($dataSourceIntegration);

        // add to $updateDataSourceIntegrations
        $this->updateDataSourceIntegrations[] = $dataSourceIntegration;
    }

    /**
     * @param PostFlushEventArgs $args
     */
    public function postFlush(PostFlushEventArgs $args)
    {
        if (count($this->updateDataSourceIntegrations) < 1) {
            return;
        }

        $em = $args->getEntityManager();
        foreach ($this->updateDataSourceIntegrations as $dataSourceIntegration) {
            $em->persist($dataSourceIntegration);
        }

        // reset $updateDataSourceIntegrations
        $this->updateDataSourceIntegrations = [];

        // flush changes
        $em->flush();
    }

    /**
     * @param DataSourceIntegrationInterface $dataSourceIntegration
     * @return DataSourceIntegrationInterface
     */
    private function updateDataSourceIntegrationSchedule(DataSourceIntegrationInterface $dataSourceIntegration)
    {
        // update uuid to schedule setting, TODO: move to new listener
        $scheduleSetting = $dataSourceIntegration->getSchedule();
        $scheduleSetting = $this->organizeScheduleSetting($scheduleSetting);

        // update to dataSourceIntegration
        $dataSourceIntegration->setSchedule($scheduleSetting);
        $lastExecuted = $dataSourceIntegration->getLastExecutedAt();

        $checkType = $scheduleSetting[DataSourceIntegration::SCHEDULE_KEY_CHECKED];
        $checkValue = $scheduleSetting[$checkType];

        switch ($checkType) {
            case DataSourceIntegration::SCHEDULE_CHECKED_CHECK_EVERY:
                $nextExecuteAt = $this->dateTimeUtil->getNextExecutedByCheckEvery($lastExecuted, $checkValue);
                $newDataSourceIntegrationSchedules = [
                    (new DataSourceIntegrationSchedule())
                        ->setUuid($checkValue[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_UUID])
                        ->setExecutedAt($nextExecuteAt)
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