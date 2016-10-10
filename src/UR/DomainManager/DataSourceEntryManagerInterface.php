<?php

namespace UR\DomainManager;

use Symfony\Component\HttpFoundation\FileBag;
use UR\Model\Core\DataSourceInterface;

interface DataSourceEntryManagerInterface extends ManagerInterface
{
    /**
     * @param FileBag $files
     * @param string $path
     * @param DataSourceInterface $dataSource
     * @return array
     */
    public function uploadDataSourceEntryFiles($files, $path, $dataSource);
}