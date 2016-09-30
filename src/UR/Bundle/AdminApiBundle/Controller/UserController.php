<?php

namespace UR\Bundle\AdminApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Bundle\AdminApiBundle\Handler\UserHandlerInterface;
use UR\Bundle\ApiBundle\Controller\RestControllerAbstract;
use UR\Bundle\UserBundle\DomainManager\PublisherManagerInterface;
use UR\Model\User\Role\PublisherInterface;

class UserController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all publisher
     * @Rest\View(serializerGroups={"user.detail","user.billing"})
     * @Rest\Get("/users")
     * @Rest\QueryParam(name="all", requirements="(true|false)", nullable=true)
     * @ApiDoc(
     *  section = "admin",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @return \UR\Bundle\UserBundle\Entity\User[]
     */
    public function cgetAction()
    {
        $paramFetcher = $this->get('fos_rest.request.param_fetcher');
        $all = $paramFetcher->get('all');

        if ($all === null || !filter_var($all, FILTER_VALIDATE_BOOLEAN)) {
            return $this->getHandler()->allActivePublishers();
        }

        return $this->getHandler()->allPublishers();
    }

    /**
     * Get a single publisher for the given id
     * @Rest\View(serializerGroups={"user.detail", "user.billing"})
     * @ApiDoc(
     *  section = "admin",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful",
     *      404 = "Returned when the resource is not found"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return \UR\Bundle\UserBundle\Entity\User
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Get token for publisher only
     *
     * @ApiDoc(
     *  section = "admin",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param $publisherId
     * @return array
     */
    public function getTokenAction($publisherId)
    {
        /** @var PublisherManagerInterface $publisherManager */
        $publisherManager = $this->get('ur_user.domain_manager.publisher');

        /** @var PublisherInterface $publisher */
        $publisher = $publisherManager->findPublisher($publisherId);

        if (!$publisher) {
            throw new NotFoundHttpException('That publisher does not exist');
        }

        $jwtManager = $this->get('lexik_jwt_authentication.jwt_manager');
        $jwtTransformer = $this->get('ur_api.service.jwt_response_transformer');

        $tokenString = $jwtManager->create($publisher);

        return $jwtTransformer->transform(['token' => $tokenString], $publisher);
    }

    /**
     * Create a user from the submitted data
     *
     * @ApiDoc(
     *  section = "admin",
     *  resource = true,
     *  parameters={
     *      {"name"="username", "dataType"="string", "required"=true},
     *      {"name"="email", "dataType"="string", "required"=false},
     *      {"name"="plainPassword", "dataType"="string", "required"=true},
     *      {"name"="role", "dataType"="string", "required"=true, "default"="publisher", "description"="The role of the user, i.e publisher or admin"},
     *      {"name"="features", "dataType"="array", "required"=false, "description"="An array of enabled features for this user, not applicable to admins"},
     *      {"name"="enabled", "dataType"="boolean", "required"=false, "description"="Is this user account enabled or not?"},
     *  },
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
     * Update an existing user from the submitted data or create a new publisher
     *
     * @ApiDoc(
     *  section = "admin",
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
     * Update an existing user from the submitted data or create a new publisher at a specific location
     *
     * @ApiDoc(
     *  section = "admin",
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
     * Delete an existing publisher
     *
     * @ApiDoc(
     *  section = "admin",
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
     * @inheritdoc
     */
    protected function getResourceName()
    {
        return 'user';
    }

    /**
     * @inheritdoc
     */
    protected function getGETRouteName()
    {
        return 'admin_api_1_get_user';
    }

    /**
     * @return UserHandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_admin_api.handler.user');
    }
}
