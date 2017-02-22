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
     * @param UploadedFile $file
     * @param string $path
     * @param string $dirItem
     * @param DataSourceInterface $dataSource
     * @param string $receivedVia one of values "upload", "api", "email" or "selenium". Default is "upload"
     * @param bool $alsoMoveFile true if move file from tmp, else if need keep file
     * @param null $metadata
     * @return array [ <original_name> => message ]
     */
    public function uploadDataSourceEntryFile(UploadedFile $file, $path, $dirItem, DataSourceInterface $dataSource, $receivedVia = DataSourceEntry::RECEIVED_VIA_UPLOAD, $alsoMoveFile = true, $metadata = null);

    /**
     * @param PublisherInterface $publisher
     * @param null $limit
     * @param null $offset
     * @return mixed
     */
    public function getDataSourceEntryForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);
}