<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\IntegrationTagInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * @Rest\RouteResource("IntegrationTag")
 */
class IntegrationTagController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all integrationTags
     *
     * @Rest\View(serializerGroups={"integration_tag.summary"})
     *
     * @ApiDoc(
     *  section = "IntegrationTag",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @return IntegrationTagInterface[]
     */
    public function cgetAction()
    {
        return $this->all();
    }

    /**
     * Get a single integrationTag group for the given id
     *
     * @Rest\View(serializerGroups={"integration_tag.edit"})
     *
     * @ApiDoc(
     *  section = "IntegrationTag",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return IntegrationTagInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Create a integrationTag from the submitted data
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @ApiDoc(
     *  section = "IntegrationTag",
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
     * Update an existing integrationTag from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "IntegrationTag",
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
     * Update an existing integrationTag from the submitted data or create a new integrationTag at a specific location
     *
     * @ApiDoc(
     *  section = "IntegrationTag",
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
     * Delete an existing integrationTag
     *
     * @ApiDoc(
     *  section = "IntegrationTag",
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
        $this->delete($id);
    }

    /**
     * @return string
     */
    protected function getResourceName()
    {
        return 'integration_tag';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_integrationtag';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.integration_tag');
    }
}