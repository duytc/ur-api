<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Bundle\ApiBundle\Behaviors\GetEntityFromIdTrait;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\Parser\Transformer\Column\NumberFormat;
use UR\Service\Parser\Transformer\TransformerFactory;
use UR\Service\PublicSimpleException;

/**
 * @Rest\RouteResource("importhistory")
 */
class ImportHistoryController extends RestControllerAbstract implements ClassResourceInterface
{
    use GetEntityFromIdTrait;

    /**
     * Get all import history
     *
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
     *  section = "import history",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\ImportHistoryInterface[]
     */
    public function cgetAction(Request $request)
    {
        $user = $this->getUserDueToQueryParamPublisher($request, 'publisher');

        $importHistoryRepository = $this->get('ur.repository.import_history');
        $qb = $importHistoryRepository->getImportHistoriesForUserPaginationQuery($user, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a single import history for the given id
     *
     * @Rest\View(serializerGroups={"importHistory.detail", "user.summary", "dataset.importhistory", "dataSourceEntry.summary", "datasource.importhistory"})
     *
     * @ApiDoc(
     *  section = "Data Source Entry",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return \UR\Model\Core\ImportHistoryInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Delete an existing import history
     *
     * @ApiDoc(
     *  section = "Data Sources Entry",
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
     * Download a Import History
     *
     * @Rest\Get("/importhistories/{id}/download" )
     *
     * @ApiDoc(
     *  section = "Imported Data",
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
    public function downloadImportedDataAction($id)
    {
        /**@var ImportHistoryInterface $importHistory */
        $importHistory = $this->one($id);
        $importHistoryRepository = $this->get('ur.repository.import_history');
        $results = $importHistoryRepository->getImportedData($importHistory);

        return $this->buildImportedData($results, $importHistory);
    }

    /**
     * Undo a Import History, delete all data of this import history on imported table
     *
     * @Rest\Get("/importhistories/{id}/undo" )
     *
     * @ApiDoc(
     *  section = "Imported Data",
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
     * @throws PublicSimpleException
     */
    public function undoImportedHistoryAction($id)
    {
        /**@var ImportHistoryInterface $importHistory */
        $importHistory = $this->one($id);
        $dataSetId = $importHistory->getDataSet()->getId();

        $workerManager = $this->get('ur.worker.manager');
        $workerManager->undoImportHistories([$importHistory->getId()], $dataSetId);

        $this->delete($id);
    }

    /**
     * @return string
     */
    protected function getResourceName()
    {
        return 'importhistory';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_importhistory';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.import_history');
    }

    private function buildImportedData(array $results, ImportHistoryInterface $importHistory)
    {
        $fields = $importHistory->getDataSet()->getAllDimensionMetrics();

        if (count($results) < 1) {
            return [array_fill_keys(array_keys($fields), "")];
        }

        $connDataSources = $importHistory->getDataSet()->getConnectedDataSources();
        $connDataSource = null;
        foreach ($connDataSources as $item) {
            $dataSourceEntry = $importHistory->getDataSourceEntry();
            if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
                break;
            }
            if ($item->getDataSource()->getId() === $dataSourceEntry->getDataSource()->getId()) {
                $connDataSource = $item;
                break;
            }
        }

        if ($connDataSource === null) {
            return $results;
        }

        $transformFactory = new TransformerFactory();
        foreach ($results as &$row) {
            foreach ($connDataSource->getTransforms() as $transform) {
                $transformObject = $transformFactory->getTransform($transform);

                if ($transformObject instanceof DateFormat) {
                    $row[$transformObject->getField()] = $transformObject->transformFromDatabaseToClient($row[$transformObject->getField()]);
                    continue;
                }

                if ($transformObject instanceof NumberFormat) {
                    $row[$transformObject->getField()] = $transformObject->transform($row[$transformObject->getField()]);
                }
            }
        }

        return $results;
    }
}