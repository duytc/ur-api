<?php

namespace UR\Bundle\ApiBundle\Controller;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Pubvantage\Worker\JobCounterInterface;
use Pubvantage\Worker\Scheduler\DataSetJobScheduler;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Bundle\ApiBundle\Behaviors\GetEntityFromIdTrait;
use UR\Exception\InvalidArgumentException;
use UR\Handler\HandlerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Model\Core\DataSetInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Service\DataSet\ReloadParams;

/**
 * @Rest\RouteResource("DataSet")
 */
class DataSetController extends RestControllerAbstract implements ClassResourceInterface
{
    use GetEntityFromIdTrait;

    /**
     * Get all data sets
     *
     * @Rest\View(serializerGroups={"dataset.summary", "user.summary", "connectedDataSource.summary", "datasource.dateRangeDetectionEnable"})
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
        $params = array_merge($request->query->all(), $request->attributes->all());
        $dataSetRepository = $this->get('ur.repository.data_set');
        $hasConnectedDataSource = $request->query->get('hasConnectedDataSource', null);

        if ($hasConnectedDataSource) {
            $hasConnectedDataSource = strtolower($hasConnectedDataSource);
        }

        if ($hasConnectedDataSource !== 'true' && $hasConnectedDataSource !== 'false' && !is_null($hasConnectedDataSource)) {
            throw new \Exception(sprintf('hasConnectedDataSource is not valid', $hasConnectedDataSource));
        }

        $user = $this->getUserDueToQueryParamPublisher($request, 'publisher');

        $qb = $dataSetRepository->getDataSetsForUserPaginationQuery($user, $this->getParams(), $hasConnectedDataSource);

        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a single data set for the given id
     *
     * @Rest\Get("/datasets/{id}", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"dataset.edit", "user.summary", "datasource.summary", "map_builder_config.summary"})
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
     * Get a pending jobs for data sets
     *
     * @Rest\Get("/datasets/pendingjobs")
     * @Rest\QueryParam(name="ids", nullable=false, description="data set ids")
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
     * @return array
     *
     */
    public function getPendingJobsAction(Request $request)
    {
        $dataSetIdsString = $request->query->get('ids', null);
        if (empty($dataSetIdsString)) {
            throw new BadRequestHttpException('Invalid ids, expected array');
        }

        $dataSetIds = explode(',', $dataSetIdsString);

        $pendingJobs = [];
        foreach ($dataSetIds as $dataSetId) {
            $dataSet = $this->one($dataSetId);
            if (!$dataSet instanceof DataSetInterface) {
                continue;
            }

            $pendingJobs[$dataSetId] = $this->getPendingJobsForDataSet($dataSet);
        }

        return $pendingJobs;
    }

    /**
     * Get all rows of map builder data set
     *
     * @Rest\Get("/datasets/{id}/rows", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"dataset.summary"})
     *
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     *
     * @ApiDoc(
     *  section = "Data Set",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param $request Request
     * @param $id integer
     * @return \UR\Model\Core\DataSetInterface[]
     * @throws \Exception
     */
    public function cgetRowsMapBuilderAction(Request $request, $id)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);

        $params = $this->getParams();
        $filters = json_decode($request->query->get('filters', ''), true);

        return $this->get('ur.service.data_mapping_manager')->getRows($dataSet, $params, $filters);
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
        $result = $this->post($request);

        return array('id' => $result->getRouteParameters()['id']);
    }

    /**
     * @Rest\Post("/datasets/{id}/matching", requirements={"id" = "\d+"})
     * @Rest\QueryParam(name="leftSide", requirements="\d+", nullable=true, description="left id")
     * @Rest\QueryParam(array=true, name="rightSide", requirements="\d+", nullable=true, description="list uniques")
     *
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function postMapTagsAction($id, Request $request)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);
        $matching = [
            'leftSide' => $request->request->get('leftSide'),
            'rightSide' => $request->request->get('rightSide')
        ];
        $this->get('ur.service.data_mapping_service')->mapTags($dataSet, $matching);
    }

    /**
     * @Rest\Post("/datasets/{id}/unmatching", requirements={"id" = "\d+"})
     * @Rest\QueryParam(name="rowId", requirements="\d+", nullable=true, description="left id")
     * @Rest\QueryParam(name="__is_left_side", requirements="\d+", nullable=true, description="Is Left Side or Right Side")
     *
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function postUnMapTagsAction($id, Request $request)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);
        $rowId = $request->request->get('rowId');
        $isLeftSide = $request->request->get('__is_left_side');

        $this->get('ur.service.data_mapping_service')->unMapTags($dataSet, $rowId, $isLeftSide);
    }

    /**
     * @Rest\Post("/datasets/{id}/rows/update", requirements={"id" = "\d+"})
     * @Rest\QueryParam(name="isIgnore", requirements="\d+", nullable=true, description="field isIgnore")
     * @Rest\QueryParam(name="rowId", requirements="\d+", nullable=true, description="field isIgnore")
     *
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function postUpdateRowsAction($id, Request $request)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);
        $rowId = $request->request->get('rowId');

        $params = [];

        if ($request->request->has('isIgnore')) {
            $params[DataSetInterface::MAPPING_IS_IGNORED] = $request->request->get('isIgnore');
        }

        $this->get('ur.service.data_mapping_manager')->updateRow($dataSet, $rowId, $params);
    }

    /**
     * @param Request $request
     * @param $id
     * @return mixed
     */
    public function postReloadAction(Request $request, $id)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);

        $reloadType = $request->request->get('option');
        $reloadStartDate = $request->request->get('startDate');
        $reloadEndDate = $request->request->get('endDate');
        $reloadParameter = new ReloadParams($reloadType, $reloadStartDate, $reloadEndDate);

        // check if this is augmentation data set and still has a non-up-to-date mapped data set
        if ($dataSet->hasNonUpToDateMappedDataSets()) {
            throw new BadRequestHttpException('There are some non-up-to-date mapped data sets relate to this data set. Please reload them before this data set.');
        }

        $workerManager = $this->get('ur.worker.manager');
        $dataMappingService = $this->get('ur.service.data_mapping_service');

        if ($dataSet->isMapBuilderEnabled()) {
            //Check config is correct or not
            if ($dataMappingService->validateMapBuilderConfigs($dataSet)) {
                $workerManager->loadFilesIntoDataSetMapBuilder($dataSet->getId());
            }
        } else {
            $workerManager->reloadDataSetByDateRange($dataSet, $reloadParameter);
        }

        /** @var EntityManagerInterface $em */
        $em = $this->get('doctrine.orm.entity_manager');
        $augmentationMappingService = $this->get('ur.service.augmentation_mapping_service');

        $augmentationMappingService->noticeChangesInLeftRightMapBuilder($dataSet, $em);
        $augmentationMappingService->noticeChangesInDataSetMapBuilder($dataSet, $em);

        return ['pendingLoads' => $this->getPendingLoadFilesForDataSet($dataSet, $reloadParameter)];
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
     */
    public function postTruncateAction($id)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);
        $workerManager = $this->get('ur.worker.manager');
        $workerManager->removeAllDataFromDataSet($dataSet->getId());

        return true;
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
     * Get a pending jobs for data sets
     *
     * @param DataSetInterface $dataSet
     * @return int
     */
    private function getPendingJobsForDataSet(DataSetInterface $dataSet)
    {
        /** @var JobCounterInterface $jobCounter */
        $jobCounter = $this->get('ur.pubvantage.worker.job_counter');

        $key = DataSetJobScheduler::getDataSetTubeName($dataSet->getId());
        $pendingJob = $jobCounter->getPendingJobCount($key);

        return $pendingJob;
    }

    /**
     * Get a pending load files for data sets
     *
     * @param DataSetInterface $dataSet
     * @param ReloadParams $reloadParams
     * @return int
     * @throws \Exception
     */
    private function getPendingLoadFilesForDataSet(DataSetInterface $dataSet, ReloadParams $reloadParams)
    {
        $dataSetTableUtil = $this->get('ur.service.data_set.table_util');
        $connectedDataSources = $dataSet->getConnectedDataSources();
        if ($connectedDataSources instanceof Collection) {
            $connectedDataSources = $connectedDataSources->toArray();
        }

        $pendingLoads = 0;

        foreach ($connectedDataSources as $connectedDataSource) {
            if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
                continue;
            }

            $entryIds = $dataSetTableUtil->getEntriesByReloadParameter($connectedDataSource, $reloadParams);
            $pendingLoads += count($entryIds);
        }

        return $pendingLoads;
    }
}