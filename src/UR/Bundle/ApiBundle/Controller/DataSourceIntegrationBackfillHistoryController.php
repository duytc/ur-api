<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\DataSourceIntegrationBackfillHistoryManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\DataSourceIntegrationBackfillHistory;
use UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface;

/**
 * @Rest\RouteResource("DataSourceIntegrationBackfillHistory")
 */
class DataSourceIntegrationBackfillHistoryController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all data sources integration backfill history
     *
     * @Rest\View(serializerGroups={"dataSourceIntegrationBackfillHistory.detail"})
     *
     * @Rest\QueryParam(name="dataSourceIntegrationBackfillHistoryId", nullable=true, requirements="\d+", description="the dataSource Integration Backfill History id")
     *
     * @ApiDoc(
     *  section = "Data Source Integration Backfill History",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\DataSourceIntegrationBackfillHistoryInterface[]
     */
    public function cgetAction(Request $request)
    {
        $params = array_merge($request->query->all(), $request->attributes->all());
        if (isset($params['$dataSourceIntegrationId'])) {
            $dataSourceIntegrationId = $params['$dataSourceIntegrationId'];
            /** @var DataSourceIntegrationBackfillHistoryManagerInterface $dsisManager */
            $dsiManager = $this->get('ur.domain_manager.data_source_integration_backfill_history');
            return $dsiManager->findByDataSourceIntegration($dataSourceIntegrationId);
        } else {
            return $this->all();
        }
    }

    /**
     * Get a single data source integration backfill history for the given id
     *
     * @Rest\View(serializerGroups={"dataSourceIntegrationBackfillHistory.detail", "dataSourceIntegration.detail", "user.summary"})
     *
     * @ApiDoc(
     *  section = "Data Source Integration Backfill History",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return mixed
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }


    /**
     * Create a data source integration backfill history from the submitted data
     *
     * @ApiDoc(
     *  section = "Data Sources Integration Backfill History",
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
     * update executeAt time
     *
     * @Rest\Post("/datasourceintegrationsbackfillhistories/{id}/update")
     *
     * @ApiDoc(
     *  section = "Data Source Integration Backfill History",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param $id
     * @param Request $request
     * @return \UR\Model\Core\DataSourceIntegrationBackfillHistory[]
     * @throws InvalidArgumentException
     * @internal param Request $request
     */
    public function postUpdateAction($id, Request $request)
    {
        /** @var DataSourceIntegrationBackfillHistoryInterface $dataSourceIntegrationBackFillHistory */
        $dataSourceIntegrationBackFillHistory = $this->one($id);

        // required param
        $statusParam = $request->request->get(DataSourceIntegrationBackfillHistoryInterface::FIELD_STATUS, null);
        if (null === $statusParam || !in_array($statusParam, DataSourceIntegrationBackfillHistory::$SUPPORTED_STATUS)) {
            throw new InvalidArgumentException('missing status or status is invalid');
        }

        $dataSourceIntegrationBackFillHistory->setStatus($statusParam);

        $utcTimeZone = new \DateTimeZone('UTC');
        $nowInUTC = new \DateTime('now', $utcTimeZone);

        /** important: if status is finish and failed -> set autoCreate to false */
        switch ($statusParam) {
            case DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_NOT_RUN:
                break;
            case DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_PENDING:
                $dataSourceIntegrationBackFillHistory->setQueuedAt($nowInUTC);
                break;
            case DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_FINISHED:
                $dataSourceIntegrationBackFillHistory->setFinishedAt($nowInUTC);
                $dataSourceIntegrationBackFillHistory->setAutoCreate(false);
                break;
            case DataSourceIntegrationBackfillHistoryInterface::FETCHER_STATUS_FAILED:
                $dataSourceIntegrationBackFillHistory->setFinishedAt($nowInUTC);
                $dataSourceIntegrationBackFillHistory->setAutoCreate(false);
                break;
        }

        $dataSourceIntegrationBackFillHistoryManager = $this->get('ur.domain_manager.data_source_integration_backfill_history');
        $dataSourceIntegrationBackFillHistoryManager->save($dataSourceIntegrationBackFillHistory);

        return $this->view(true, Codes::HTTP_OK);
    }

    /**
     * Update an existing data source integration backfill history from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "Data Sources Integration Backfill History",
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
     * Update an existing data source integration backfill history from the submitted data or create a new data source at a specific location
     *
     * @ApiDoc(
     *  section = "Data Sources Integration Backfill History",
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
        return $this->patch($request, $id);
    }

    /**
     * Delete an existing data source integration backfill history
     *
     * @ApiDoc(
     *  section = "Data Sources Integration Backfill History",
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
        return 'datasourceintegrationbackfillhistory';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_datasourceintegrationbackfillhistory';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.data_source_integration_backfill_history');
    }
}