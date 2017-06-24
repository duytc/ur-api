<?php

namespace UR\Repository\Core;

use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\QueryBuilder;
use UR\Model\Core\DataSetInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\IntegrationInterface;
use UR\Model\PagerParam;
use UR\Model\User\Role\PublisherInterface;
use UR\Model\User\Role\UserRoleInterface;

interface DataSourceRepositoryInterface extends ObjectRepository
{
    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return array
     */
    public function getDataSourcesForPublisher(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param PublisherInterface $publisher
     * @param int|null $limit
     * @param int|null $offset
     * @return QueryBuilder
     */
    public function getDataSourcesForPublisherQuery(PublisherInterface $publisher, $limit = null, $offset = null);

    /**
     * @param string $apiKey
     * @return DataSourceInterface
     */
    public function getDataSourceByApiKey($apiKey);

    /**
     * @param UserRoleInterface $userRole
     * @param PagerParam $param
     * @return QueryBuilder
     */
    public function getDataSourcesForUserQuery(UserRoleInterface $userRole, PagerParam $param);

    /**
     * @param string $emailKey
     * @return DataSourceInterface
     */
    public function getDataSourceByEmailKey($emailKey);

    /**
     * @param DataSetInterface $dataSet
     * @return array
     */
    public function getDataSourceByDataSet(DataSetInterface $dataSet);

    /**
     * @param DataSetInterface $dataSet
     * @return DataSourceInterface[]
     */
    public function getDataSourceNotInByDataSet(DataSetInterface $dataSet);

    /**
     * get DataSources By Integration And Publisher. This is used for integration integration modules into ur system...
     *
     * @param IntegrationInterface $integration
     * @param PublisherInterface $publisher
     * @return DataSourceInterface[]
     */
    public function getDataSourcesByIntegrationAndPublisher(IntegrationInterface $integration, PublisherInterface $publisher);

    /**
     * @return DataSourceInterface[]
     */
    public function getDataSourcesHasDailyAlert();

    /**
     * @param array $dataSetIds
     * @return mixed
     */
    public function getBrokenDateRangeDataSourceForDataSets(array $dataSetIds);
}