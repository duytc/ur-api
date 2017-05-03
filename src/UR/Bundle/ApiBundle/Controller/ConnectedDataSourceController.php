<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Entity\Core\DataSourceEntry;
use UR\Handler\HandlerInterface;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use UR\Service\Import\AutoImportDataInterface;
use UR\Service\Import\PublicImportDataException;

/**
 * @Rest\RouteResource("ConnectedDataSource")
 */
class ConnectedDataSourceController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all connectedDataSource
     *
     * @Rest\View(serializerGroups={"connectedDataSource.summary", "datasource.detail"})
     *
     * @ApiDoc(
     *  section = "ConnectedDataSource",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @return ConnectedDataSourceInterface[]
     */
    public function cgetAction()
    {
        return $this->all();
    }

    /**
     * Get a single connectedDataSource group for the given id
     *
     * @Rest\View(serializerGroups={"connectedDataSource.edit", "datasource.dataset", "dataset.detail", "user.summary"})
     *
     * @ApiDoc(
     *  section = "ConnectedDataSource",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return ConnectedDataSourceInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Create a connectedDataSource from the submitted data
     *
     * @ApiDoc(
     *  section = "ConnectedDataSource",
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
     * @return View
     */
    public function postCloneAction(Request $request, $id)
    {
        /** @var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $this->one($id);
        $name = $request->request->get('name', null);

        /** @var ConnectedDataSourceInterface $newConnectedDataSource */
        $newConnectedDataSource = clone $connectedDataSource;
        $newConnectedDataSource->setId(null);
        $newConnectedDataSource->setReplayData(false); //explicitly set to FALSE to avoid automatically inserting new data

        if (empty($name)) {
            $name = $connectedDataSource->getDataSource()->getName();
        }

        $newConnectedDataSource->setName($name);

        $this->get('ur.domain_manager.connected_data_source')->save($newConnectedDataSource);

        return $this->view(null, Codes::HTTP_CREATED);
    }

    /**
     * @Rest\Post("/connecteddatasources/dryrun")
     *
     * @param Request $request
     * @return mixed
     */
    public function postDryRunAction(Request $request)
    {
        $filePaths = $request->request->get('filePaths', null);

        // temporary create connected data source entity (not save to database)
        // do this because the new connected data source (not yet save) may be difference from old connected data source in database
        $postResult = $this->postAndReturnEntityData($request);
        /** @var ConnectedDataSourceInterface $tempConnectedDataSource */
        $tempConnectedDataSource = $postResult->getData();

        return $this->handleDryRun($tempConnectedDataSource, $filePaths);
    }

    /**
     * @param Request $request
     * @param $id
     * @return bool
     */
    public function postReloadalldataAction(Request $request, $id)
    {
        /** @var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $this->one($id);

        $entries = $connectedDataSource->getDataSource()->getDataSourceEntries();
        $entryIds = array_map(function (DataSourceEntryInterface $entry) {
            return $entry->getId();
        }, $entries->toArray());

        $loadingDataService = $this->get('ur.service.loading_data_service');
        $loadingDataService->doLoadDataFromEntryToDataBase($connectedDataSource, $entryIds);

        return true;
    }

    /**
     * Update an existing connectedDataSource from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "ConnectedDataSource",
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
     * Update an existing connectedDataSource from the submitted data or create a new connectedDataSource at a specific location
     *
     * @ApiDoc(
     *  section = "ConnectedDataSource",
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
     * Delete an existing connectedDataSource
     *
     * @ApiDoc(
     *  section = "ConnectedDataSource",
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
        return 'connecteddatasource';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_connecteddatasource';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.connected_data_source');
    }

    /**
     * @param ConnectedDataSourceInterface $tempConnectedDataSource
     * @param $filePaths
     * @return mixed
     * @throws PublicImportDataException
     */
    private function handleDryRun(ConnectedDataSourceInterface $tempConnectedDataSource, $filePaths)
    {
        $dataSourceEntryManager = $this->get('ur.repository.data_source_entry');
        $lastDataSourceEntry = $dataSourceEntryManager->getLastDataSourceEntryForConnectedDataSource($tempConnectedDataSource->getDataSource());

        if (($filePaths === null || count($filePaths) < 1) && $lastDataSourceEntry === null) {

            $result = [
                AbstractConnectedDataSourceAlert::CODE => AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_NO_FILE_PREVIEW,
                AbstractConnectedDataSourceAlert::DETAILS => []
            ];

            throw new PublicImportDataException($result, new BadRequestHttpException(json_encode($result)));
        }

        // call auto import for dry run only
        /** @var AutoImportDataInterface $autoImportService */
        $autoImportService = $this->get('ur.worker.workers.auto_import_data');

        if (is_array($filePaths) && count($filePaths) > 0) {// check if connected data source is creating new
            $dataSourceEntry = new DataSourceEntry();
            $dataSourceEntry->setPath($filePaths[0]);
        } else {// use last entry
            $dataSourceEntry = $lastDataSourceEntry;
        }

        return $autoImportService->createDryRunImportData($tempConnectedDataSource, $dataSourceEntry);
    }
}