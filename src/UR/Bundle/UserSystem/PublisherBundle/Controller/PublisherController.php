<?php

namespace UR\Bundle\UserSystem\PublisherBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Bundle\AdminApiBundle\Handler\UserHandlerInterface;
use UR\Bundle\ApiBundle\Controller\RestControllerAbstract;
use UR\Exception\LogicException;
use UR\Model\User\Role\PublisherInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;

/**
 * @Rest\RouteResource("publishers/current")
 */
class PublisherController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get current publisher
     * @Rest\View(
     *      serializerGroups={"user.detail"}
     * )
     * @return \UR\Bundle\UserBundle\Entity\User
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction()
    {
        $publisherId = $this->get('security.context')->getToken()->getUser()->getId();

        return $this->one($publisherId);
    }

    /**
     * Update current publisher from the submitted data
     *
     * @param Request $request the request object
     *
     * @return FormTypeInterface|View
     *
     * @throws NotFoundHttpException when resource not exist
     */
    public function patchAction(Request $request)
    {
        $publisherId = $this->get('security.context')->getToken()->getUser()->getId();

        return $this->patch($request, $publisherId);
    }

    /**
     * get account as Publisher by publisherId
     * @Rest\View(
     *      serializerGroups={"user.detail"}
     * )
     * @param integer $publisherId
     * @return PublisherInterface Publisher
     */
    protected function getPublisher($publisherId)
    {
        try {
            $publisher = $this->one($publisherId);
        } catch (\Exception $e) {
            $publisher = false;
        }

        if (!$publisher instanceof PublisherInterface) {
            throw new LogicException('The user should have the publisher role');
        }

        return $publisher;
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
        return 'publisher_api_1_get_current';
    }

    /**
     * @return UserHandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_admin_api.handler.user');
    }
}
