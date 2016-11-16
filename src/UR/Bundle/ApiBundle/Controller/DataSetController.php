<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Exception\InvalidArgumentException;
use UR\Handler\HandlerInterface;
use UR\Model\Core\DataSetInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;

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
     */
    public function cgetAction(Request $request)
    {
        $publisher = $this->getUser();

        $dataSetRepository = $this->get('ur.repository.data_set');
        $qb = $dataSetRepository->getDataSetsForUserPaginationQuery($publisher, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
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
     * @Rest\View(serializerGroups={"datasource.summary","dataset.summary"})
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
     * @ApiDoc(
     *  section = "Data Source",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     * @return array
     * @throws \Exception
     */
    public function getImportHistoriesByDataSetAction($id)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);
        $importHistoryManager = $this->get('ur.domain_manager.import_history');

        return $importHistoryManager->getImportedDataByDataSet($dataSet);
    }

    /**
     * Get all connected data sources of a data set
     *
     * @Rest\Get("datasets/{id}/connecteddatasources", requirements={"id" = "\d+"})
     * @Rest\View(serializerGroups={"connectedDataSource.summary", "datasource.summary"})
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
     * @return array
     */
    public function getConnectedDataSourceByDataSetAction($id)
    {
        /** @var DataSetInterface $dataSet */
        $dataSet = $this->one($id);

        $connectedDataSourceManager = $this->get('ur.domain_manager.connected_data_source');
        $connectedDataSource = $connectedDataSourceManager->getConnectedDataSourceByDataSet($dataSet);

        return $connectedDataSource;
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
}