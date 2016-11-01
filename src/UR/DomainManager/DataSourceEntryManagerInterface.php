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
    public function uploadDataSourceEntryFiles($files, $path, $dirItem, $dataSource);

    /**
     * @param PublisherInterface $publisher
     * @param null $limit
     * @param null $offset
     * @return mixed
     */
    public function getDataSourceEntryForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);
}