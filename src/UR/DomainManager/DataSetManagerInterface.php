<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSetManagerInterface extends ManagerInterface
{
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return DataSetInterface[]
     */
    public function getDataSetForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param DataSourceInterface $dataSource
     * @return DataSetInterface[]
     */
    public function getDataSetByDataSource(DataSourceInterface $dataSource);

    /**
     * @param $dataSetName
     * @return mixed
     */
    public function getDataSetByName($dataSetName);
}