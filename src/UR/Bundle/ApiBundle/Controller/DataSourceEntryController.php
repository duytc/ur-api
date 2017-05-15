<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use UR\DomainManager\ImportHistoryManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Handler\HandlerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use UR\Model\Core\DataSourceInterface;
use UR\Model\Core\ImportHistoryInterface;
use UR\Service\PublicSimpleException;

/**
 * @Rest\RouteResource("datasourceentry")
 */
class DataSourceEntryController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all data source entries
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceEntry.summary", "user.summary"})
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
     *  section = "Data Source Entry",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\DataSourceEntryInterface[]
     */
    public function cgetAction(Request $request)
    {
        $publisher = $this->getUser();

        $dataSourceEntryRepository = $this->get('ur.repository.data_source_entry');
        $qb = $dataSourceEntryRepository->getDataSourceEntriesForUserQuery($publisher, $this->getParams());

        $params = array_merge($request->query->all(), $request->attributes->all());
        if (!isset($params['page'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a single data source entry for the given id
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceEntry.summary", "user.summary"})
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
     * @return \UR\Model\Core\DataSourceEntryInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Download a DataSource Entry
     *
     * @Rest\Get("/datasourceentries/{id}/download" )
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
     * @return mixed
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function downloadFileAction($id)
    {
        $dataSourceEntryManager = $this->get('ur.domain_manager.data_source_entry');

        /**@var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $dataSourceEntryManager->find($id);

        $filePath = $this->checkFileExist($dataSourceEntry);
        $response = new Response();
        $response->headers->set('Cache-Control', 'private');
        $response->headers->set('Content-type', 'application/download');
        $response->headers->set('Content-Disposition', 'inline; filename="' . basename($filePath) . '"');
        $response->setContent(file_get_contents($filePath));

        return $response;
    }

    /**
     * Get preview of single data source entry without any transform
     *
     * @Rest\Get("/datasourceentries/{id}/preview", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"datasource.summary", "dataSourceIntegration.summary", "integration.summary", "user.summary"})
     *
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="limit number rows to display")
     *
     * @ApiDoc(
     *  section = "Data Source Entry",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @param int $id the resource id
     * @return mixed
     */
    public function getPreviewentryAction(Request $request, $id)
    {
        $dataSourceEntry = $this->one($id);

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            throw new AccessDeniedException(
                sprintf(
                    'You do not have permission to data source entry %s or it does not exist',
                    $id
                )
            );
        }

        $params = array_merge($request->query->all(), $request->attributes->all());

        $limit = 100;
        if (isset($params['limit'])) {
            $limit = (int)$params['limit'];
        }

        $dataSourceEntryPreview = $this->get('ur.service.data_source_entry_preview_service');
        return $dataSourceEntryPreview->preview($dataSourceEntry, $limit);
    }

    /**
     * Create a data source entry from the submitted data
     *
     * @ApiDoc(
     *  section = "Data Sources Entry",
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
     * Replay Data of an Entry
     *
     * @Rest\Post("/datasourceentries/{id}/replaydata" )
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
     * @return mixed
     * @throws \Exception
     */
    public function postReplayDataAction($id)
    {
        /** @var DataSourceEntryInterface $dataSourceEntry */
        $dataSourceEntry = $this->one($id);

        $this->checkFileExist($dataSourceEntry);

        return $this->replayDataSourceEntryData([$dataSourceEntry->getId()], $dataSourceEntry->getDataSource());
    }

    /**
     * Update an existing data source entry from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "Data Sources Entry",
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
     * Update an array existing data source entries
     *
     * @Rest\Put("/datasourceentries")
     * @Rest\QueryParam(name="replay", requirements="(true|false)", nullable=true, description="replay data source entries")
     *
     * Update an array existing data source entries
     *
     * @ApiDoc(
     *  section = "Data Sources Entry",
     *  resource = true,
     *  statusCodes = {
     *      201 = "Returned when the resource is created",
     *      204 = "Returned when successful",
     *      400 = "Returned when the submitted data has errors"
     *  }
     * )
     *
     * @param Request $request
     * @return mixed
     * @throws \Exception
     */
    public function putDataSourceEntriesAction(Request $request)
    {
        $params = $request->request->all();
        $ids = $params['ids'];

        $replay = $request->query->get('replay', null);
        $replay = ($replay === 'true') ? true : false;

        if ($replay === true) {
            $entryIds = [];

            foreach ($ids as $id) {
                /**@var DataSourceEntryInterface $dataSourceEntry */
                $dataSourceEntry = $this->one($id);
                $entryIds[] = $dataSourceEntry->getId();
                $dataSource = $dataSourceEntry->getDataSource();
            }

            if (count($entryIds) > 0) {
                /**@var DataSourceInterface $dataSource */
                $this->replayDataSourceEntryData($entryIds, $dataSource);
            }
        }
    }

    /**
     * Update an existing data source entry from the submitted data or create a new data source at a specific location
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
     * Delete an existing data source entry
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
     * @throws \Exception
     */
    public function deleteAction($id)
    {
        $dataSourceEntry = $this->getHandler()->get($id);

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            throw new \Exception(sprintf('Data Source Entry %d does not exist', $id));
        }

        /** @var ImportHistoryManagerInterface $importHistoryManager */
        $importHistoryManager = $this->get('ur.domain_manager.import_history');
        $importHistories = $importHistoryManager->getImportHistoryByDataSourceEntryWithoutDataSet($dataSourceEntry);

        $dataSets = array_map(function ($importHistory) {
            /**@var ImportHistoryInterface $importHistory */
            return $importHistory->getDataSet()->getName();
        }, $importHistories);

        $dataSets = array_values(array_unique($dataSets));

        if (count($dataSets) > 0) {
            throw new PublicSimpleException(sprintf('Loaded data cannot be deleted. If you wish to continue, you must unload the file "%s" from all data sets', $dataSourceEntry->getFileName()));
        }

        return $this->delete($id);
    }

    /**
     * @Rest\Post("/datasourceentries/delete")
     * @param Request $request
     * @return Response
     */
    public function deleteMultipleEntriesAction(Request $request)
    {
        $entries = $request->request->get('entries', null);
        if (empty($entries) || !is_array($entries)) {
            throw new InvalidArgumentException('"entries" should be an array of data source entry\'s id');
        }

        foreach ($entries as $entry) {
            $entry = $this->one($entry);
            $this->getHandler()->delete($entry);
        }

        $view = $this->view(null, Codes::HTTP_NO_CONTENT);

        return $this->handleView($view);
    }

    /**
     * @return string
     */
    protected function getResourceName()
    {
        return 'datasourceentry';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_datasourceentry';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.data_source_entry');
    }

    protected function checkFileExist(DataSourceEntryInterface $dataSourceEntry)
    {
        $uploadRootDir = $this->container->getParameter('upload_file_dir');
        $filePath = $uploadRootDir . $dataSourceEntry->getPath();
        if (!file_exists($filePath)) {
            throw new Exception('The file was not found or you do not have access');
        }

        return $filePath;
    }

    /**
     * @param array $entryIds
     * @param DataSourceInterface $dataSource
     * @throws \Exception
     */
    private function replayDataSourceEntryData(array $entryIds, DataSourceInterface $dataSource)
    {
        if (!$dataSource->getEnable()) {
            throw new \Exception(sprintf("Could not replay data - entry in disabled Data Source "));
        }

        asort($entryIds);

        $loadingDataService = $this->get('ur.service.loading_data_service');
        foreach ($dataSource->getConnectedDataSources() as $connectedDataSource) {
            $loadingDataService->doLoadDataFromEntryToDataBase($connectedDataSource, $entryIds);
        }
    }
}