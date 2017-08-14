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
use UR\Model\Core\TagInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

/**
 * @Rest\RouteResource("Tag")
 */
class TagController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all tags
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @Rest\View(serializerGroups={"tag.summary"})
     *
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchField", nullable=true, description="field to filter, must match field in Entity")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     *
     * @ApiDoc(
     *  section = "Tag",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\TagInterface[]
     */
    public function cgetAction(Request $request)
    {
        $user = $this->getUser();
        $params = array_merge($request->query->all(), $request->attributes->all());

        $reportViewTemplateRepository = $this->get('ur.repository.tag');
        $qb = $reportViewTemplateRepository->getTagsForUserPaginationQuery($user, $this->getParams());

        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a single tag group for the given id
     *
     * @Rest\View(serializerGroups={"tag.edit", "user.minimum", "user_tag.edit", "report_view_template_tag.edit", "report_view_template.minimum", "integration_tag.summary", "integration.summary"})
     *
     * @ApiDoc(
     *  section = "Tag",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return TagInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Get all user tags of a tag
     *
     * @Rest\Get("/tags/{id}/usertags" )
     * @Rest\View(serializerGroups={"user_tag.detail", "tag.detail", "user.uuid"})
     *
     * @ApiDoc(
     *  section = "Tag",
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
    public function getUserTagsByTagAction($id)
    {
        /** @var TagInterface $tag */
        $tag = $this->one($id);
        return $this->get('ur.domain_manager.user_tag')->findByTag($tag);
    }

    /**
     * Get all integration tags of a tag
     *
     * @Rest\Get("/tags/{id}/integrationtags" )
     * @Rest\View(serializerGroups={"integration_tag.detail", "tag.detail", "integration.summary"})
     *
     * @ApiDoc(
     *  section = "Tag",
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
    public function getIntegrationTagsByTagAction($id)
    {
        /** @var TagInterface $tag */
        $tag = $this->one($id);
        return $this->get('ur.domain_manager.integration_tag')->findByTag($tag);
    }

    /**
     * Get all integrations of a tag
     *
     * @Rest\Get("/tags/{id}/integrations" )
     * @Rest\View(serializerGroups={"integration_tag.detail", "tag.detail", "integration.summary"})
     *
     * @ApiDoc(
     *  section = "Tag",
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
    public function getIntegrationsByTagAction($id)
    {
        /** @var TagInterface $tag */
        $tag = $this->one($id);
        return $this->get('ur.domain_manager.integration')->findByTag($tag);
    }

    /**
     * Get all report view template tags of a tag
     *
     * @Rest\Get("/tags/{id}/reportviewtemplatetags" )
     * @Rest\View(serializerGroups={"report_view_template_tag.detail", "tag.detail", "report_view_template.summary"})
     *
     * @ApiDoc(
     *  section = "Tag",
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
    public function getReportViewTemplateTagsByTagAction($id)
    {
        /** @var TagInterface $tag */
        $tag = $this->one($id);
        return $this->get('ur.domain_manager.report_view_template_tag')->findByTag($tag);
    }

    /**
     * Get all report view templates of a tag
     *
     * @Rest\Get("/tags/{id}/reportviewtemplates" )
     * @Rest\View(serializerGroups={"report_view_template_tag.detail", "tag.detail", "report_view_template.summary"})
     *
     * @ApiDoc(
     *  section = "Tag",
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
    public function getReportViewTemplatesByTagAction($id)
    {
        /** @var TagInterface $tag */
        $tag = $this->one($id);
        return $this->get('ur.domain_manager.report_view_template')->findByTag($tag);
    }

    /**
     * Create a tag from the submitted data
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @ApiDoc(
     *  section = "Tag",
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
     * Update an existing tag from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "Tag",
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
     * Update an existing tag from the submitted data or create a new tag at a specific location
     *
     * @ApiDoc(
     *  section = "Tag",
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
     * Delete an existing tag
     *
     * @ApiDoc(
     *  section = "Tag",
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
        return 'tag';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_tag';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.tag');
    }
}