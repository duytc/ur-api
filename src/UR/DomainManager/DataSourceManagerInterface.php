<?php

namespace UR\DomainManager;

use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\User\Role\PublisherInterface;

interface DataSourceManagerInterface extends ManagerInterface
{
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return DataSourceInterface[]
     */
    public function getDataSourceForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param string $apiKey
     * @return DataSourceInterface
     */
    public function getDataSourceByApiKey($apiKey);

    /**
     * @param DataSetInterface $dataSet
     * @return DataSourceInterface[]
     */
    public function getDataSourceByDataSet(DataSetInterface $dataSet);

    /**
     * @param DataSetInterface $dataSet
     * @return DataSourceInterface[]
     */
    public function getDataSourceNotInByDataSet(DataSetInterface $dataSet);

    /**
     * @param string $email
     * @return DataSourceInterface|null
     */
    public function findByEmail($email);

    /**
     * @return DataSourceInterface[]
     */
    public function getDataSourcesHasDailyAlert();
}