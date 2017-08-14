<?php

namespace UR\Bundle\ApiBundle\Controller;

use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Routing\ClassResourceInterface;
use FOS\RestBundle\Util\Codes;
use FOS\RestBundle\View\View;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UR\Entity\Core\ReportViewTemplateTag;
use UR\Entity\Core\Tag;
use UR\Handler\HandlerInterface;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Psr\Log\LoggerInterface;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\Core\ReportViewTemplateTagInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use UR\Model\Core\TagInterface;
use UR\Model\User\Role\AdminInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\PublicSimpleException;
use UR\Service\ReportViewTemplate\DTO\CustomTemplateParams;

/**
 * @Rest\RouteResource("ReportViewTemplate")
 */
class ReportViewTemplateController extends RestControllerAbstract implements ClassResourceInterface
{
    /**
     * Get all reportViewTemplates
     *
     * @Rest\View(serializerGroups={"report_view_template.minimum"})
     *
     * @Rest\QueryParam(name="page", requirements="\d+", nullable=true, description="the page to get")
     * @Rest\QueryParam(name="limit", requirements="\d+", nullable=true, description="number of item per page")
     * @Rest\QueryParam(name="searchField", nullable=true, description="field to filter, must match field in Entity")
     * @Rest\QueryParam(name="searchKey", nullable=true, description="value of above filter")
     * @Rest\QueryParam(name="sortField", nullable=true, description="field to sort, must match field in Entity and sortable")
     * @Rest\QueryParam(name="orderBy", nullable=true, description="value of sort direction : asc or desc")
     *
     * @ApiDoc(
     *  section = "ReportViewTemplate",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param Request $request
     * @return \UR\Model\Core\ReportViewTemplateInterface[]
     */
    public function cgetAction(Request $request)
    {
        $user = $this->getUser();
        $params = array_merge($request->query->all(), $request->attributes->all());

        $reportViewTemplateRepository = $this->get('ur.repository.report_view_template');
        $qb = $reportViewTemplateRepository->getReportViewTemplatesForUserPaginationQuery($user, $this->getParams());

        if (!isset($params['page']) && !isset($params['sortField']) && !isset($params['orderBy']) && !isset($params['searchKey'])) {
            return $qb->getQuery()->getResult();
        } else {
            return $this->getPagination($qb, $request);
        }
    }

    /**
     * Get a single reportViewTemplate group for the given id
     *
     * @Rest\View(serializerGroups={"report_view_template.edit", "user.minimum", "user_tag.edit", "report_view_template_tag.tag", "report_view_template.minimum", "integration_tag.summary", "integration.summary", "tag.minimum"})
     *
     * @ApiDoc(
     *  section = "ReportViewTemplate",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  }
     * )
     *
     * @param int $id the resource id
     *
     * @return ReportViewTemplateInterface
     * @throws NotFoundHttpException when the resource does not exist
     */
    public function getAction($id)
    {
        return $this->one($id);
    }

    /**
     * Get all report view template tags of a report view template
     *
     * @Rest\Get("/reportviewtemplates/{id}/reportviewtemplatetags")
     * @Rest\View(serializerGroups={"report_view_template_tag.detail", "tag.detail", "report_view_template.summary"})
     *
     * @ApiDoc(
     *  section = "Report View Template",
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
    public function getReportViewTemplateTagsByReportViewTemplateAction($id)
    {
        /** @var ReportViewTemplateInterface $reportViewTemplate */
        $reportViewTemplate = $this->one($id);
        return $this->get('ur.domain_manager.report_view_template_tag')->findByReportViewTemplate($reportViewTemplate);
    }

    /**
     * Get all tags of a report view template
     *
     * @Rest\Get("/reportviewtemplates/{id}/tags")
     * @Rest\View(serializerGroups={"report_view_template_tag.detail", "tag.detail", "report_view_template.summary"})
     *
     * @ApiDoc(
     *  section = "Report View Template",
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
    public function getTagsByReportViewTemplateAction($id)
    {
        /** @var ReportViewTemplateInterface $reportViewTemplate */
        $reportViewTemplate = $this->one($id);
        return $this->get('ur.domain_manager.tag')->findByReportViewTemplate($reportViewTemplate);
    }

    /**
     * Get all publishers can view a report view template
     *
     * @Rest\Get("/reportviewtemplates/{id}/publishers")
     * @Rest\View(serializerGroups={"report_view_template_tag.detail", "tag.detail", "report_view_template.summary", "user.detail"})
     *
     * @ApiDoc(
     *  section = "Report View Template",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     * )
     *
     * @param int $id the resource id
     * @return PublisherInterface[]
     * @throws \Exception
     */
    public function getPublishersByReportViewTemplateAction($id)
    {
        /** @var ReportViewTemplateInterface $reportViewTemplate */
        $reportViewTemplate = $this->one($id);

        return $this->get('ur_user.domain_manager.publisher')->findByReportViewTemplate($reportViewTemplate);
    }

    /**
     * Create a reportViewTemplate from the submitted data
     *
     * @Security("has_role('ROLE_ADMIN')")
     *
     * @ApiDoc(
     *  section = "ReportViewTemplate",
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
     * Create a report view from report view template
     *
     * @Rest\Post("/reportviewtemplates/{id}/toreportview")
     *
     * @ApiDoc(
     *  section = "Report View Template",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     * )
     *
     * @param int $id the resource id
     * @param Request $request
     * @return string
     * @throws \Exception
     */
    public function postCreateReportViewAction($id, Request $request)
    {
        /** @var ReportViewTemplateInterface $reportViewTemplate */
        $reportViewTemplate = $this->one($id);

        $publisher = $this->getUser();
        if (!$publisher instanceof PublisherInterface) {
            $publisher = $this->get('ur_user.domain_manager.publisher')->find($request->request->get('publisher'));
        }

        if (!$publisher instanceof PublisherInterface) {
            throw new PublicSimpleException('Missing param publisher');
        }

        $customParams = new CustomTemplateParams();
        $customParams->setName($request->request->get('name', $reportViewTemplate->getName()));

        $this->get('ur.service.report_view_template.report_view_template_service')->createReportViewFromReportViewTemplate($reportViewTemplate, $publisher, $customParams);
    }

    /**
     * Update report view template
     *
     * @Rest\Post("/reportviewtemplates/{id}/update")
     *
     * @ApiDoc(
     *  section = "Report View Template",
     *  resource = true,
     *  statusCodes = {
     *      200 = "Returned when successful"
     *  },
     * )
     *
     * @param int $id the resource id
     * @param Request $request
     * @return string
     * @throws \Exception
     */
    public function postUpdateReportViewTemplateAction($id, Request $request)
    {
        /** @var ReportViewTemplateInterface $reportViewTemplate */
        $reportViewTemplate = $this->one($id);

        /** Get services */
        $tagManager = $this->get('ur.domain_manager.tag');
        $reportViewTemplateTagManager = $this->get('ur.domain_manager.report_view_template_tag');
        $reportViewTemplateManager = $this->get('ur.domain_manager.report_view_template');

        /** Update $name */
        $name = $request->request->get('name', $reportViewTemplate->getName());
        $reportViewTemplate->setName($name);

        /** Delete existing report view template tag */
        foreach ($reportViewTemplate->getReportViewTemplateTags() as $reportViewTemplateTag) {
            $reportViewTemplateTagManager->delete($reportViewTemplateTag);
        }

        /** Add new report view template tags*/
        $nameTags = $request->request->get('tags', []);
        $reportViewTemplateTags = [];

        foreach ($nameTags as $nameTag) {
            $existTag = $tagManager->findByName($nameTag);

            if (!$existTag instanceof TagInterface) {
                $existTag = new Tag();
                $existTag->setName($nameTag);
            }

            $reportViewTemplateTag = new ReportViewTemplateTag();
            $reportViewTemplateTag->setTag($existTag);
            $reportViewTemplateTag->setReportViewTemplate($reportViewTemplate);
            $reportViewTemplateTags[] = $reportViewTemplateTag;
        }
        $reportViewTemplate->setReportViewTemplateTags($reportViewTemplateTags);

        $reportViewTemplateManager->save($reportViewTemplate);
    }

    /**
     * Update an existing reportViewTemplate from the submitted data or create a new ad network
     *
     * @ApiDoc(
     *  section = "ReportViewTemplate",
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
     * Update an existing reportViewTemplate from the submitted data or create a new reportViewTemplate at a specific location
     *
     * @ApiDoc(
     *  section = "ReportViewTemplate",
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
        /** @var ReportViewTemplateInterface $reportViewTemplate */
        $reportViewTemplate = $this->one($id);

        /** Get services */
        $tagManager = $this->get('ur.domain_manager.tag');
        $reportViewTemplateTagManager = $this->get('ur.domain_manager.report_view_template_tag');

        /** Delete existing report view template tag */
        foreach ($reportViewTemplate->getReportViewTemplateTags() as $reportViewTemplateTag) {
            $reportViewTemplateTagManager->delete($reportViewTemplateTag);
        }

        /** Remove tags params because form not allow extra fields */
        $nameTags = $request->request->get('tags', []);
        $request->request->remove('tags');

        /** Add new report view template tags*/
        $reportViewTemplateTags = [];
        foreach ($nameTags as $nameTag) {
            $existTag = $tagManager->findByName($nameTag);

            if (!$existTag instanceof TagInterface) {
                $existTag = new Tag();
                $existTag->setName($nameTag);
                $tagManager->save($existTag);
            }

            $reportViewTemplateTag = $reportViewTemplateTagManager->findByReportViewTemplateAndTag($reportViewTemplate, $existTag);

            if (!$reportViewTemplateTag instanceof ReportViewTemplateTagInterface) {
                $reportViewTemplateTag = new ReportViewTemplateTag();
                $reportViewTemplateTag->setTag($existTag);
                $reportViewTemplateTag->setReportViewTemplate($reportViewTemplate);
                $reportViewTemplateTagManager->save($reportViewTemplateTag);
            }

            $reportViewTemplateTags[] = $existTag->getId();
        }

        try {
            return $this->patch($request, $id);
        } catch (\Exception $e) {

        }

        return Codes::HTTP_ACCEPTED;
    }

    /**
     * Delete an existing reportViewTemplate
     *
     * @ApiDoc(
     *  section = "ReportViewTemplate",
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
        return 'report_view_template';
    }

    /**
     * The 'get' route name to redirect to after resource creation
     *
     * @return string
     */
    protected function getGETRouteName()
    {
        return 'api_1_get_reportviewtemplate';
    }

    /**
     * @return HandlerInterface
     */
    protected function getHandler()
    {
        return $this->container->get('ur_api.handler.report_view_template');
    }
}