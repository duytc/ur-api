<?php

namespace UR\Behaviors;


use DateTime;
use Doctrine\Common\Collections\Collection;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\PublicSimpleException;

trait ReloadDataUtilTrait
{
    /**
     * @param ConnectedDataSourceManagerInterface $connectedDataSourceManager
     * @param $connectedDataSources
     * @param $reloadStartDate
     * @param $reloadEndDate
     * @throws PublicSimpleException
     */
    protected function setReloadDateForConnectedDataSources(ConnectedDataSourceManagerInterface $connectedDataSourceManager, $connectedDataSources, $reloadStartDate, $reloadEndDate)
    {
        if (!is_null($reloadStartDate) || !is_null($reloadEndDate)) {
            $reloadStartDate = date_create_from_format('Y-m-d', $reloadStartDate);
            $reloadEndDate = date_create_from_format('Y-m-d', $reloadEndDate);

            if (!$reloadStartDate instanceof DateTime ||
                !$reloadEndDate instanceof DateTime
            ) {
                throw new PublicSimpleException("Wrong format for reload start date and reload end date");
            }

            $reloadStartDate->setTime(0, 0, 0);
            $reloadEndDate->setTime(0, 0, 0);
        }


        if ($connectedDataSources instanceof Collection) {
            $connectedDataSources = $connectedDataSources->toArray();
        }

        if (!is_array($connectedDataSources)) {
            return;
        }

        foreach ($connectedDataSources as $connectedDataSource) {
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }
            $connectedDataSource->setReloadStartDate($reloadStartDate);
            $connectedDataSource->setReloadEndDate($reloadEndDate);

            $connectedDataSourceManager->save($connectedDataSource);
        }
    }
}