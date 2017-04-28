<?php

namespace UR\Bundle\ApiBundle\EventListener;


use DateInterval;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Alert\DataSource\DataSourceAlertFactory;
use UR\Service\Alert\DataSource\NoDataReceivedDailyAlert;

class UpdateNextAlertTimeForDataSourceListener
{
    public function preUpdate(PreUpdateEventArgs $args)
    {
        $entity = $args->getEntity();
        if (!$entity instanceof DataSourceInterface) {
            return;
        }

        if (!$args->hasChangedField('alertSetting') && $args->hasChangedField('nextAlertTime')) {
            /**
             * @var \DateTime $oldDateTime
             */
            $oldDateTime = $args->getOldValue('nextAlertTime');
            $entity->setNextAlertTime($oldDateTime->add(new DateInterval('P1D')));
        } else {
            $this->setNextTimeAlert($entity);
        }
    }

    public function prePersist(LifecycleEventArgs $args)
    {
        $entity = $args->getEntity();

        /*
         * create new data source
         */
        if ($entity instanceof DataSourceInterface) {
            $this->setNextTimeAlert($entity);
        }

    }

    protected function setNextTimeAlert(DataSourceInterface $dataSource)
    {
        $alertFactory = new DataSourceAlertFactory();
        /**
         * @var NoDataReceivedDailyAlert $alert
         */
        $alert = $alertFactory->getAlert(AlertInterface::ALERT_CODE_NO_DATA_RECEIVED_DAILY, null, $dataSource);

        if (!$alert instanceof NoDataReceivedDailyAlert) {
            $dataSource->setNextAlertTime(null);
            return $dataSource;
        }

        $dataSource->setNextAlertTime($alert->getNextAlertTime());
        return $dataSource;
    }
}