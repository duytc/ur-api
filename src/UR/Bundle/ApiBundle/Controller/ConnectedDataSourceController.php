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
use UR\Domain\DTO\ConnectedDataSource\DryRunParamsInterface;
use UR\Entity\Core\DataSourceEntry;
use UR\Handler\HandlerInterface;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use UR\Service\DataSet\ReloadParams;
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
        $newConnectedDataSource->setTotalRow(0);

        if (empty($name)) {
            $name = $connectedDataSource->getDataSource()->getName();
        }

        $newConnectedDataSource->setName($name);

        // update number of changes
        $newConnectedDataSource->setNumChanges(1);
        $this->get('ur.domain_manager.connected_data_source')->save($newConnectedDataSource);

        $dataSet = $newConnectedDataSource->getDataSet();
        $dataSet->increaseNumConnectedDataSourceChanges();
        $this->get('ur.domain_manager.data_set')->save($dataSet);

        return $this->view(null, Codes::HTTP_CREATED);
    }

    /**
     * @Rest\Post("/connecteddatasources/dryrun")
     *
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchField", nullable=true, description="field to filter, must match field in Entity")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     * @Rest\QueryParam(name="limitRows", requirements="\d+", nullable=true, description="maximum report rows (100, 200, 500, 1000, ...)")
     *
     * @param Request $request
     * @return mixed
     */
    public function postDryRunAction(Request $request)
    {
        /* file for previewing data (using path of uploaded file or existing data source entry)*/
        $pathOrDataSourceEntryId = $request->request->get('dataSourceEntryId', null);
        $dryRunParams = $this->get('ur.services.dry_run_params_builder')->buildFromArray($request->request->all());

        // temporary create connected data source entity (not save to database)
        // do this because the new connected data source (not yet save) may be difference from old connected data source in database
        $postResult = $this->postAndReturnEntityData($request);
        /** @var ConnectedDataSourceInterface $tempConnectedDataSource */
        $tempConnectedDataSource = $postResult->getData();

        return $this->handleDryRun($tempConnectedDataSource, $pathOrDataSourceEntryId, $dryRunParams);
    }

    /**
     * @param Request $request
     * @param $id
     * @return bool
     */
    public function postReloadAction(Request $request, $id)
    {
        /** @var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $this->one($id);

        $reloadType = $request->request->get('option');
        $reloadStartDate = $request->request->get('startDate');
        $reloadEndDate = $request->request->get('endDate');
        $reloadParameter = new ReloadParams($reloadType, $reloadStartDate, $reloadEndDate);

        $manager = $this->get('ur.worker.manager');
        // check if this is augmentation data set and still has a non-up-to-date mapped data set
        $dataSet = $connectedDataSource->getDataSet();
        if ($dataSet->hasNonUpToDateMappedDataSetsByConnectedDataSource($connectedDataSource)) {
            throw new BadRequestHttpException('There are some non-up-to-date mapped data sets relate to the data set of this connected data source. Please reload them before this connected data source.');
        }

        $manager->reloadConnectedDataSourceByDateRange($connectedDataSource, $reloadParameter);

        return true;
    }

    /**
     * Remove All data from connected data source
     *
     * @Rest\Post("connecteddatasources/{id}/removealldatas", requirements={"id" = "\d+"})
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
    public function postRemoveAllDataAction($id)
    {
        /** @var ConnectedDataSourceInterface $connectedDataSource */
        $connectedDataSource = $this->one($id);
        $manager = $this->get('ur.worker.manager');
        $manager->removeAllDataFromConnectedDataSource($connectedDataSource->getId(), $connectedDataSource->getDataSet()->getId());

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
        $connectedDataSource = $this->one($id);

        if (!$connectedDataSource instanceof ConnectedDataSourceInterface) {
            throw new NotFoundHttpException(
                sprintf("The connected data source with ID '%s' was not found or you do not have access", $id)
            );
        }

        $connectedDataSourceId = $connectedDataSource->getId();
        $dataSetId = $connectedDataSource->getDataSet()->getId();

        $this->get('ur.worker.manager')->deleteConnectedDataSource($connectedDataSourceId, $dataSetId);
        $this->delete($id);

        $view = $this->view(null, Codes::HTTP_NO_CONTENT);

        return $this->handleView($view);
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
     * @param $pathOrDataSourceEntryId
     * @param DryRunParamsInterface $dryRunParams
     * @return mixed
     * @throws PublicImportDataException
     */
    private function handleDryRun(ConnectedDataSourceInterface $tempConnectedDataSource, $pathOrDataSourceEntryId, DryRunParamsInterface $dryRunParams)
    {
        $dataSourceEntryManager = $this->get('ur.repository.data_source_entry');
        $selectedEntry = null;
        if (is_numeric($pathOrDataSourceEntryId)) {
            $selectedEntry = $dataSourceEntryManager->find($pathOrDataSourceEntryId);
        } else if (!empty($pathOrDataSourceEntryId)) {
            $selectedEntry = new DataSourceEntry();
            $selectedEntry->setPath($pathOrDataSourceEntryId);
        }

        if ($selectedEntry === null) {
            $result = [
                AbstractConnectedDataSourceAlert::CODE => AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_NO_FILE_PREVIEW,
                AbstractConnectedDataSourceAlert::DETAILS => []
            ];

            throw new PublicImportDataException($result, new BadRequestHttpException(json_encode($result)));
        }

        // call auto import for dry run only
        /** @var AutoImportDataInterface $autoImportService */
        $autoImportService = $this->get('ur.service.auto_import_data');

        return $autoImportService->createDryRunImportData($tempConnectedDataSource, $selectedEntry, $dryRunParams);
    }
}