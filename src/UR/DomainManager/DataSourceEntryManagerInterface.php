<?php

namespace UR\DomainManager;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\FileBag;
use UR\Model\Core\DataSourceEntry;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSourceEntryManagerInterface extends ManagerInterface
{
    /**
     * @param array|UploadedFile $files
     * @param string $path
     * @param string $dirItem
     * @param DataSourceInterface $dataSource
     * @param string $receivedVia one of values "upload", "api", "email" or "selenium". Default is "upload"
     * @return array
     */
    public function uploadDataSourceEntryFiles(array $files, $path, $dirItem, DataSourceInterface $dataSource, $receivedVia = DataSourceEntry::RECEIVED_VIA_UPLOAD);

    /**
     * @param FileBag $files
     * @param $uploadPath
     * @param $dirItem
     * @param DataSourceInterface $dataSource
     * @return array
     * @internal param DataSourceInterface $dataSource
     */
    public function detectedFieldsFromFiles(FileBag $files, $uploadPath, $dirItem, DataSourceInterface $dataSource);

    /**
     * @param PublisherInterface $publisher
     * @param null $limit
     * @param null $offset
     * @return mixed
     */
    public function getDataSourceEntryForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);
}