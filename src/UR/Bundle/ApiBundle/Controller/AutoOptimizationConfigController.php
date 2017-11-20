<?php


namespace UR\Bundle\ApiBundle\Controller;


use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Handler\HandlerInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use FOS\RestBundle\Controller\Annotations as Rest;

/**
 * @Rest\RouteResource("autoOptimizationConfig")
 */
class AutoOptimizationConfigController extends RestControllerAbstract implements ClassResourceInterface
{

    /**
     * Get all auto optimization config
     *
     * @Rest\View(serializerGroups={"auto_optimization_config.summary"})
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @return AutoOptimizationConfigInterface[]
     */
    public function cgetAction()
    {
        return $this->all();
    }

    /**
     * Get a single auto optimization config for the given id
     *
     * @Rest\View(serializerGroups={"auto_optimization_config.detail", "user.summary", "auto_optimization_config_data_set.summary", "dataset.summary"})
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return AutoOptimizationConfigInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Create a auto optimization config from the submitted data
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
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
     * Update an existing auto optimization config from the submitted data or create a new one
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
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
     * Update an existing auto optimization config from the submitted data or create a new one at a specific location
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
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
     * Delete an existing auto optimization config
     *
     * @ApiDoc(
     *  section = "AutoOptimizationConfig",
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
        return 'autoOptimizationConfig';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_autooptimizationconfig';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.auto_optimization_config');
    }
}