<?php

namespace UR\Worker\Workers;


use UR\DomainManager\AlertManagerInterface;
use UR\Entity\Core\Alert;

class AlertWhenConnectedDataSourceChangeWorker
{
    /**
     * @var AlertManagerInterface
     */
    private $alertManager;

    public function __construct(AlertManagerInterface $alertManager)
    {
        $this->alertManager = $alertManager;
    }

    public function alertWhenConnectedDataSourceChange(StdClass $params)
    {
        $importedDataAlert = new Alert();
//        $importedDataAlert->setDataSourceEntry($item);
//        $importedDataAlert->setTitle($title);
//        $importedDataAlert->setType($type);
//        $importedDataAlert->setMessage($message);
//        $this->alertManager->save($importedDataAlert);
    }

}