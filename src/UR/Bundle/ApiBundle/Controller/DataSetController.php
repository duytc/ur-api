<?php

namespace UR\Bundle\ApiBundle\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Comparator;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Exception\InvalidArgumentException;
use UR\Handler\HandlerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetImportJobInterface;
use UR\Model\Core\DataSetInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\DataSet\Synchronizer;

/**
 * @Rest\RouteResource("DataSet")
 */
class DataSetController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all data sets
     *
     * @Rest\View(serializerGroups={"dataset.detail", "user.summary", "connectedDataSource.summary"})
     *
     * @Rest\QueryParam(name="publisher", nullable=true, requirements="\d+", description="the publisher id")
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchField", nullable=true, description="field to filter, must match field in Entity")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     * @Rest\QueryParam(name="hasConnectedDataSource", nullable=true, description="has connected data source option")
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\DataSetInterface[]
     * @throws \Exception
     */
    public function cgetAction(Request $request)
    {
        $publisher = $this->getUser();
        $params = array_merge($request->query->all(), $request->attributes->all());
        $dataSetRepository = $this->get('ur.repository.data_set');
        $hasConnectedDataSource = $request->query->get('hasConnectedDataSource', null);

        if ($hasConnectedDataSource) {
            $hasConnectedDataSource = strtolower($hasConnectedDataSource);
        }

        if ($hasConnectedDataSource !== 'true' && $hasConnectedDataSource !== 'false' && !is_null($hasConnectedDataSource)) {
            throw new \Exception(sprintf('hasConnectedDataSource is not valid', $hasConnectedDataSource));
        }

        if ($publisher instanceof AdminInterface) {
            if (isset($params['publisher'])) {
                $publisherId = filter_var($params['publisher'], FILTER_VALIDATE_INT);
                $user = $this->get('ur_user.domain_manager.publisher')->find($publisherId);
                if (!$user instanceof PublisherInterface) {
                    throw new InvalidArgumentException(sprintf('publisher %d does not exist', $publisherId));
                }

                $publisher = $user;
            }
        }

        $qb = $dataSetRepository->getDataSetsForUserPaginationQuery($publisher, $this->getParams(), $hasConnectedDataSource);

        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get number rows of all data sets
     *
     * @Rest\Get("/datasets/rows", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"dataset.detail", "user.summary", "connectedDataSource.summary"})
     *
     * @Rest\QueryParam(name="publisher", nullable=true, requirements="\d+", description="the publisher id")
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchField", nullable=true, description="field to filter, must match field in Entity")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     * @Rest\QueryParam(name="hasConnectedDataSource", nullable=true, description="has connected data source option")
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function cgetCountRowsAction(Request $request)
    {
        $dataSets = $this->cgetAction($request);

        $params = array_merge($request->query->all(), $request->attributes->all());

        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $this->getNumberOfRows($dataSets, false);
        } else {
            return $this->getNumberOfRows($dataSets, true);
        }
    }

    /**
     * Get number rows of all data sets
     *
     * @Rest\Get("/datasets/{id}/rows", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"dataset.detail", "user.summary", "connectedDataSource.summary"})
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param integer $id
     * @return mixed
     * @throws \Exception
     */
    public function getCountRowsAction($id)
    {
        $dataSet = $this->one($id);

        if (!$dataSet instanceof DataSetInterface) {
            throw new \Exception('Data Set in invalid');
        }

        return $this->getDataSetNumberOfRows($dataSet);
    }

    /**
     * Get a single data set for the given id
     *
     * @Rest\Get("/datasets/{id}", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"dataset.detail", "user.summary", "connectedDataSource.summary", "datasource.summary"})
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return \UR\Model\Core\DataSetInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Create a data set from the submitted data
     *
     * @ApiDoc(
     *  section = "Data Sources",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param Request $request the request object
     *
     * @return FormTypeInterface|View
     */
    public function postAction(Request $request)
    {
        return $this->post($request);
    }

    /**
     * @param Request $request
     * @param $id
     * @return bool
     */
    public function postReloadalldataAction(Request $request, $id)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);
        $connectedDataSources = $dataSet->getConnectedDataSources();
        $loadingDataService = $this->get('ur.service.loading_data_service');

        foreach ($connectedDataSources as $connectedDataSource) {
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            $entries = $connectedDataSource->getDataSource()->getDataSourceEntries();
            $entryIds = array_map(function (DataSourceEntryInterface $entry) {
                return $entry->getId();
            }, $entries->toArray());

            $loadingDataService->doLoadDataFromEntryToDataBase([$connectedDataSource], $entryIds);
        }

        return true;
    }


    /**
     * Update an existing data set from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "Data Sources",
     *  resource = true,
     *  statusCodes = {
     *      201 = "Returned when the resource is created",
     *      204 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param Request $request the request object
     * @param int $id the resource id
     *
     * @return FormTypeInterface|View
     *
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function putAction(Request $request, $id)
    {
        return $this->put($request, $id);
    }

    /**
     * Update an existing data set from the submitted data or create a new data set at a specific location
     *
     * @ApiDoc(
     *  section = "Data Sources",
     *  resource = true,
     *  statusCodes = {
     *      204 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param Request $request the request object
     * @param int $id the resource id
     *
     * @return FormTypeInterface|View
     *
     * @throws NotFoundHttpException when resource not exist
     */
    public function patchAction(Request $request, $id)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);

        if (array_key_exists('publisher', $request->request->all())) {
            $publisher = (int)$request->get('publisher');
            if ($dataSet->getPublisherId() != $publisher) {
                throw new InvalidArgumentException('publisher in invalid');
            }
        }

        return $this->patch($request, $id);
    }

    /**
     * Get all data sources of a data set
     *
     * @Rest\Get("datasets/{id}/datasources", requirements={"id" = "\d+"})
     * @Rest\View(serializerGroups={"datasource.dataset","dataset.summary"})
     * @Rest\QueryParam(name="connected", nullable=true, requirements="\d+", description="relation between datasource and dataset option")
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @param int $id the resource id
     * @return array
     * @throws \Exception
     */
    public function getDataSourceByDataSetAction(Request $request, $id)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);

        $connected = $request->query->get('connected', null);
        $dataSourceManager = $this->get('ur.domain_manager.data_source');

        if (is_null($connected)) {
            $dataSource = $dataSourceManager->getDataSourceForPublisher($dataSet->getPublisher());
        } else if (strtolower($connected) === 'true') {
            $dataSource = $dataSourceManager->getDataSourceByDataSet($dataSet);
        } else if (strtolower($connected) === 'false') {
            $dataSource = $dataSourceManager->getDataSourceNotInByDataSet($dataSet);
        } else {
            throw new \Exception(sprintf("Connected param %s is not valid", $connected));
        }

        return array_values($dataSource);
    }

    /**
     * Get all data sources of a data set
     *
     * @Rest\Get("datasets/{id}/importhistories", requirements={"id" = "\d+"})
     * @Rest\View(serializerGroups={"importHistory.detail", "user.summary", "dataset.importhistory", "dataSourceEntry.summary", "datasource.importhistory"})
     *
     * @Rest\QueryParam(name="publisher", nullable=true, requirements="\d+", description="the publisher id")
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchField", nullable=true, description="field to filter, must match field in Entity")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function getImportHistoriesByDataSetAction(Request $request, $id)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);
        $importHistoryManager = $this->get('ur.domain_manager.import_history');
        $qb = $importHistoryManager->getImportedHistoryByDataSetQuery($dataSet, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get all connected data sources of a data set
     *
     * @Rest\Get("datasets/{id}/connecteddatasources", requirements={"id" = "\d+"})
     * @Rest\View(serializerGroups={"connectedDataSource.summary", "datasource.summary"})
     *
     * @Rest\QueryParam(name="publisher", nullable=true, requirements="\d+", description="the publisher id")
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchField", nullable=true, description="field to filter, must match field in Entity")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     *
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     * @param Request $request
     * @return \UR\Model\Core\ConnectedDataSourceInterface
     */
    public function getConnectedDataSourceByDataSetAction(Request $request, $id)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);

        $connectedDataSourceManager = $this->get('ur.domain_manager.connected_data_source');
        $qb = $connectedDataSourceManager->getConnectedDataSourceByDataSetQuery($dataSet, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Truncate dataset table
     *
     * @Rest\Post("datasets/{id}/truncate", requirements={"id" = "\d+"})
     *
     * @ApiDoc(
     *  section = "DataSet",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param $id
     *
     * @return mixed
     * @throws \Exception
     */
    public function postTruncateAction($id)
    {
        $dataSet = $this->one($id);

        if (!$dataSet instanceof DataSetInterface) {
            throw new \Exception(sprintf('Data Set %d does not exist', $id));
        }

        $importHistoryManager = $this->get('ur.domain_manager.import_history');
        $importHistories = $importHistoryManager->getImportedHistoryByDataSet($dataSet);

        $loadingDataService = $this->get('ur.service.loading_data_service');
        $loadingDataService->undoImport($importHistories);

        return '';
    }

    /**
     * Delete an existing data set
     *
     * @ApiDoc(
     *  section = "Data Sources",
     *  resource = true,
     *  statusCodes = {
     *      204 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return View
     *
     * @throws NotFoundHttpException when the resource not exist
     */
    public function deleteAction($id)
    {
        return $this->delete($id);
    }

    /**
     * @return string
     */
    protected function getResourceName()
    {
        return 'dataset';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_dataset';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.data_set');
    }

    /**
     * @var DataSetInterface[] $dataSets
     * @var boolean $isPagination
     * @return \UR\Model\Core\DataSetInterface[]
     */
    private function getNumberOfRows($dataSets, $isPagination = false)
    {
        $realDataSets = $dataSets;
        if ($isPagination) {
            $realDataSets = $dataSets['records'];
        }

        $result = array_map(function ($dataSet) {
            /**@var DataSetInterface $dataSet */
            return [
                'id' => $dataSet->getId(),
                'numberOfRows' => $this->getDataSetNumberOfRows($dataSet)
            ];
        }, $realDataSets);

        return $result;
    }

    /**
     * @var DataSetInterface $dataSet
     * @return integer
     */
    private function getDataSetNumberOfRows($dataSet)
    {
        // hotfix disable this because of poor performance
        return '-';

        $entityManager = $this->get('doctrine.orm.entity_manager');

        /** @var Connection $conn */
        $conn = $entityManager->getConnection();
        $dataSetSynchronizer = new Synchronizer($conn, new Comparator());;

        $result = 0;
        if ($dataSet instanceof DataSetInterface) {
            $dataTable = $dataSetSynchronizer->getDataSetImportTable($dataSet->getId());

            // check if table not existed
            if ($dataTable) {
                try {
                    $countSQL = sprintf("select count(*) from %s where %s is null", $dataTable->getName(), DataSetInterface::OVERWRITE_DATE);
                    $stmt = $conn->prepare($countSQL);
                    $stmt->execute();
                    $result = $stmt->fetchAll();
                    $stmt->closeCursor();
                } catch (\Exception $e) {

                }
            }
        }

        return $result[0]['count(*)'];
    }
}