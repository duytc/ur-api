<?php

namespace UR\DomainManager;

use Symfony\Component\HttpFoundation\FileBag;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSourceEntryManagerInterface extends ManagerInterface
{
    /**
     * @param FileBag $files
     * @param string $path
     * @param string $dirItem
     * @param DataSourceInterface $dataSource
     * @return array
     */
    public function uploadDataSourceEntryFiles(FileBag $files, $path, $dirItem, DataSourceInterface $dataSource);

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