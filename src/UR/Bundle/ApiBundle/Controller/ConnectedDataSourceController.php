<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Entity\Core\DataSourceEntry;
use UR\Handler\HandlerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use UR\Service\Import\AutoImportDataInterface;

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
     * @Rest\View(serializerGroups={"connectedDataSource.detail", "datasource.dataset", "dataset.summary"})
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
     * @Rest\Post("/connecteddatasources/dryrun")
     *
     * @param Request $request
     * @return mixed
     */
    public function postDryRunAction(Request $request)
    {
        $filePaths = $request->request->get('filePaths', null);

        // temporary create connected data source entity (not save to database)
        $postResult = $this->postAndReturnEntityData($request);
        /** @var ConnectedDataSourceInterface $tempConnectedDataSource */
        $tempConnectedDataSource = $postResult->getData();


        return $this->handleDryRun($tempConnectedDataSource, $filePaths);
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
     */
    private function handleDryRun(ConnectedDataSourceInterface $tempConnectedDataSource, $filePaths)
    {
        $dataSourceEntryManager = $this->get('ur.repository.data_source_entry');
        $lastDataSourceEntry = $dataSourceEntryManager->getLastDataSourceEntryForConnectedDataSource($tempConnectedDataSource->getDataSource());

        if (($filePaths === null || count($filePaths) < 1) && $lastDataSourceEntry === null) {

            $result = [
                AbstractConnectedDataSourceAlert::CODE => AbstractConnectedDataSourceAlert::ALERT_CODE_NO_FILE_PREVIEW,
                AbstractConnectedDataSourceAlert::DETAILS => []
            ];

            $message = json_encode($result);
            throw new BadRequestHttpException($message);
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