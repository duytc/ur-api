<?php

namespace UR\Service\Alert;


use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\DomainManager\AlertManagerInterface;
use UR\DomainManager\ConnectedDataSourceManagerInterface;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\Entity\Core\Alert;

class ProcessAlert implements ProcessAlertInterface
{
    protected $alertManager;
    protected $dataSourceEntryManager;
    protected $connectedDataSource;
    protected $publisherManager;

    public function __construct(AlertManagerInterface $alertManager, DataSourceEntryManagerInterface $dataSourceEntryManager, ConnectedDataSourceManagerInterface $connectedDataSource, PublisherManagerInterface $publisherManager)
    {
        $this->alertManager = $alertManager;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->connectedDataSource = $connectedDataSource;
        $this->publisherManager = $publisherManager;
    }

    public function importAlert(array $params)
    {
        $importedDataAlert = new Alert();

        $dataSourceEntryId = $params[AlertParams::DATA_SOURCE_ENTRY];
        $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);

        $connectedDataSourceId = $params[AlertParams::CONNECTED_DATA_SOURCE];
        $connectedDataSource = $this->connectedDataSource->find($connectedDataSourceId);

        $importedDataAlert->setPublisher($dataSourceEntry->getDataSource()->getPublisher());

        $arrayPath = explode('/', $dataSourceEntry->getPath());
        $fileNameTemp = $arrayPath[count($arrayPath) - 1];
        $lastDash = strrpos($fileNameTemp, "_");
        $lastFileNamePath = substr($fileNameTemp, $lastDash);
        $arrayLastPath = explode('.', $lastFileNamePath);
        $extension = $arrayLastPath[1];
        $firstFileNamePath = substr($fileNameTemp, 0, strlen($fileNameTemp) - strlen($lastFileNamePath));
        $fileName = $firstFileNamePath . "." . $extension;
        $message = array();
        $message[AlertParams::FILE_NAME] = $fileName;
        $message[AlertParams::DATA_SOURCE_NAME] = $connectedDataSource->getDataSource()->getName();
        $message[AlertParams::DATA_SET_NAME] = $connectedDataSource->getDataSet()->getName();

        switch ($params[AlertParams::CODE]) {
            case AlertParams::IMPORT_DATA_SUCCESS :
                break;
//            $message = sprintf("File %s of %s and %s is imported", $fileName, $connectedDataSource->getDataSource()->getName(), $connectedDataSource->getDataSet()->getName());
            case AlertParams::IMPORT_DATA_FAILURE :
                $message[AlertParams::ROW] = $params['row'];
                $message[AlertParams::COLUMN] = $params['column'];
                $message[AlertParams::ERROR] = $params['error'];
                break;
//            $message = sprintf("%s", $message);
        }

        $message = json_encode($message);
        $importedDataAlert->setCode($params['code']);
        $importedDataAlert->setMessage($message);
        $this->alertManager->save($importedDataAlert);
    }

    public function uploadAlert(array $params)
    {
        $uploadedDataAlert = new Alert();
        $publishers = $this->publisherManager->findPublisher($params[AlertParams::PUBLISHER]);
        $uploadedDataAlert->setPublisher($publishers);
        $fileName = $params[AlertParams::FILE_NAME];

        $message = array();
        $message[AlertParams::FILE_NAME] = $fileName;

        switch ($params[AlertParams::CODE]) {
            case AlertParams::UPLOAD_DATA_SUCCESS :
                $dataSourceEntryId = $params[AlertParams::DATA_SOURCE_ENTRY];
                $dataSourceEntry = $this->dataSourceEntryManager->find($dataSourceEntryId);
                $message[AlertParams::DATA_SOURCE_NAME] = $dataSourceEntry->getDataSource()->getName();
//            $message = sprintf('File %s of %s is uploaded', $file_name . "." . $file->getClientOriginalExtension(), $dataSourceEntry->getDataSource()->getName());
                break;
            case AlertParams::UPLOAD_DATA_FAILURE :
                $message[AlertParams::ERROR] = $params[AlertParams::ERROR];
//            $message = sprintf('File %s is not uploaded', $fileName . "." . $file->getClientOriginalExtension());
                break;
        }

        $message = json_encode($message);
        $uploadedDataAlert->setCode($params[AlertParams::CODE]);
        $uploadedDataAlert->setMessage($message);
        $this->alertManager->save($uploadedDataAlert);
    }
}