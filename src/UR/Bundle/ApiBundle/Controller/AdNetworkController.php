<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\AdNetworkInterface;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\PublisherInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;


/**
 * @Rest\RouteResource("Adnetwork")
 */
class AdNetworkController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all ad networks
     *
     * @Rest\View(serializerGroups={"adnetwork.detail", "user.summary"})
     *
     * @Rest\QueryParam(name="publisher", nullable=true, requirements="\d+", description="the publisher id")
     *
     * @ApiDoc(
     *  section = "Ad Networks",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @return AdNetworkInterface[]
     */
    public function cgetAction()
    {
        $paramFetcher = $this->get('fos_rest.request.param_fetcher');
        $publisher = $paramFetcher->get('publisher');
        $adNetworkManager = $this->get('ur.domain_manager.ad_network');

        if ($publisher != null && $this->getUser() instanceof AdminInterface) {
            $publisher = $this->get('ur_user.domain_manager.publisher')->findPublisher($publisher);

            if (!$publisher instanceof PublisherInterface) {
                throw new NotFoundHttpException('That publisher does not exist');
            }

            $all = $adNetworkManager->getAdNetworksForPublisher($publisher);
        }

        $all = isset($all) ? $all : $this->all();

        $this->checkUserPermission($all);

        return $all;
    }

    /**
     * Get a single ad network for the given id
     *
     * @Rest\View(serializerGroups={"adnetwork.detail", "user.summary", "adtag.summary", "partner.summary"})
     *
     * @ApiDoc(
     *  section = "Ad Networks",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return \UR\Model\Core\AdNetworkInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Create a ad network from the submitted data
     *
     * @ApiDoc(
     *  section = "Ad Networks",
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
     * Update an existing ad network from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "Ad Networks",
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
     * Update an existing ad network from the submitted data or create a new ad network at a specific location
     *
     * @ApiDoc(
     *  section = "Ad Networks",
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
        /** @var AdNetworkInterface $adNetwork */
        $adNetwork = $this->one($id);

        if (array_key_exists('publisher', $request->request->all())) {
            $publisher = (int)$request->get('publisher');
            if ($adNetwork->getPublisherId() != $publisher) {
                throw new InvalidArgumentException('publisher in invalid');
            }
        }

        return $this->patch($request, $id);
    }

    /**
     * Delete an existing ad network
     *
     * @ApiDoc(
     *  section = "Ad Networks",
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

    protected function getLogger()
    {
        return $this->get('logger');
    }

    protected function getResourceName()
    {
        return 'adnetwork';
    }

    protected function getGETRouteName()
    {
        return 'api_1_get_adnetwork';
    }

    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.ad_network');
    }
}
