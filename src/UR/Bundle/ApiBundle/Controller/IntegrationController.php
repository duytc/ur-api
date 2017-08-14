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
use UR\Model\Core\IntegrationInterface;
use UR\Model\User\Role\AdminInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Repository\Core\IntegrationRepositoryInterface;

/**
 * @Rest\RouteResource("Integration")
 */
class IntegrationController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all integration
     *
     * @Rest\View(
     *     serializerGroups={
     *          "user.summary",
     *          "integration_publisher.summary",
     *          "integration.detail",
     *      }
     * )
     *
     * @ApiDoc(
     *  section = "Integration",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\IntegrationInterface[]
     */
    public function cgetAction(Request $request)
    {
        $user = $this->getUser();
        $params = array_merge($request->query->all(), $request->attributes->all());

        /** @var IntegrationRepositoryInterface $integrationRepository */
        $integrationRepository = $this->get('ur.repository.integration');
        
        $qb = $integrationRepository->getIntegrationsForUserQuery($user, $this->getParams());

        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a single integration group for the given id
     *
     * @Rest\View(serializerGroups={"integration.detail", "integration_publisher.detail"})
     *
     * @ApiDoc(
     *  section = "Integration",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return \UR\Model\Core\IntegrationInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->getOr404($id);
    }

    /**
     * Get all integration tags of a integration
     *
     * @Rest\Get("/integrations/{id}/integrationtags" )
     * @Rest\View(serializerGroups={"integration_tag.detail", "tag.detail", "integration.summary"})
     *
     * @ApiDoc(
     *  section = "Integration",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     * )
     *
     * @param int $id the resource id
     * @return string
     * @throws \Exception
     */
    public function getIntegrationTagsByIntegrationAction($id)
    {
        /** @var IntegrationInterface $integration */
        $integration = $this->one($id);
        return $this->get('ur.domain_manager.integration_tag')->findByIntegration($integration);
    }

    /**
     * Get all tags of a integration
     *
     * @Rest\Get("/integrations/{id}/tags" )
     * @Rest\View(serializerGroups={"integration_tag.detail", "tag.detail", "integration.summary"})
     *
     * @ApiDoc(
     *  section = "Integration",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     * )
     *
     * @param int $id the resource id
     * @return string
     * @throws \Exception
     */
    public function getTagsByIntegrationAction($id)
    {
        /** @var IntegrationInterface $integration */
        $integration = $this->one($id);
        return $this->get('ur.domain_manager.tag')->findByIntegration($integration);
    }

    /**
     * Create a integration from the submitted data
     *
     * @ApiDoc(
     *  section = "Integration",
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
        if (!$this->getUser() instanceof AdminInterface) {
            throw new InvalidArgumentException('only Admin has permission to create this resource');
        }

        return $this->post($request);
    }

    /**
     * Update an existing integration from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "Integration",
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
     * Update an existing integration from the submitted data or create a new integration at a specific location
     *
     * @ApiDoc(
     *  section = "Integration",
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
     * Delete an existing integration
     *
     * @ApiDoc(
     *  section = "Integration",
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
        return 'integration';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_integration';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.integration');
    }
}