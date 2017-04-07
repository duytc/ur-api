<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\DomainManager\DataSourceIntegrationManagerInterface;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\DataSourceIntegrationInterface;

/**
 * @Rest\RouteResource("datasourceintegration")
 */
class DataSourceIntegrationController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all data sources integration
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceIntegration.detail", "integration.detail", "user.summary"})
     *
     * @Rest\QueryParam(name="dataSourceId", nullable=true, requirements="\d+", description="the dataSource id")
     *
     * @ApiDoc(
     *  section = "Data Source Integration",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\DataSourceIntegrationInterface[]
     */
    public function cgetAction(Request $request)
    {
        $params = array_merge($request->query->all(), $request->attributes->all());
        if (isset($params['dataSourceId'])) {
            $dataSourceId = $params['dataSourceId'];
            /** @var DataSourceIntegrationManagerInterface $dsisManager */
            $dsiManager = $this->get('ur.domain_manager.data_source_integration');
            return $dsiManager->findByDataSource($dataSourceId);
        } else {
            return $this->all();
        }
    }

    /**
     * Get a single data source integration for the given id
     *
     * @Rest\Get("/datasourceintegrations/{id}", requirements={"id" = "\d+"})
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceIntegration.detail", "integration.detail", "user.summary"})
     *
     * @ApiDoc(
     *  section = "Data Source Integration",
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
     * Create a data source integration from the submitted data
     *
     * @ApiDoc(
     *  section = "Data Sources Integration",
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
     * update backFill executed
     *
     * @Rest\Post("/datasourceintegrations/backfill")
     *
     * @Rest\View(serializerGroups={"datasource.detail", "dataSourceIntegration.detail", "integration.detail", "user.summary"})
     *
     * @ApiDoc(
     *  section = "Data Source Integration",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\DataSourceIntegrationInterface[]
     */
    public function postUpdateBackFillExecutedAction(Request $request)
    {
        $id = $request->request->get('id', null);

        $dataSourceIntegration = $this->one($id);
        if (!($dataSourceIntegration instanceof DataSourceIntegrationInterface)) {
            throw new NotFoundHttpException('Not found that data source integration');
        }

        // update backFill Executed
        $dataSourceIntegration->setBackFillExecuted(true);
        /** @var DataSourceIntegrationManagerInterface $dsiManager */
        $dsiManager = $this->get('ur.domain_manager.data_source_integration');
        $dsiManager->save($dataSourceIntegration);

        return $this->view(true, Codes::HTTP_OK);
    }

    /**
     * Update an existing data source integration from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "Data Sources Integration",
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
     * Update an existing data source integration from the submitted data or create a new data source at a specific location
     *
     * @ApiDoc(
     *  section = "Data Sources Integration",
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
     * Delete an existing data source integration
     *
     * @ApiDoc(
     *  section = "Data Sources Integration",
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
        return 'data_source_integration';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_data_source_integration';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.data_source_integration');
    }
}