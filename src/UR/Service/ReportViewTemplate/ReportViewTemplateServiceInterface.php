<?php


namespace UR\Service\ReportViewTemplate;

use UR\Model\Core\ReportViewInterface;
use UR\Model\Core\ReportViewTemplateInterface;
use UR\Model\User\Role\PublisherInterface;
use UR\Service\ReportViewTemplate\DTO\CustomTemplateParamsInterface;

interface ReportViewTemplateServiceInterface
{
    /**
     * @param ReportViewInterface $reportView
     * @param CustomTemplateParamsInterface $customTemplateParams
     */
    public function createReportViewTemplateFromReportView(ReportViewInterface $reportView, CustomTemplateParamsInterface $customTemplateParams);

    /**
     * @param ReportViewTemplateInterface $reportViewTemplate
     * @param PublisherInterface $publisher
     * @param CustomTemplateParamsInterface $customTemplateParams
     * @return
     */
    public function createReportViewFromReportViewTemplate(ReportViewTemplateInterface $reportViewTemplate, PublisherInterface $publisher, CustomTemplateParamsInterface $customTemplateParams);

    /**
     * clone report view base on clone settings
     *
     * @param ReportViewInterface $reportView
     * @param PublisherInterface $publisher
     * @return ReportViewInterface
     */
    public function cloneReportView(ReportViewInterface $reportView, PublisherInterface $publisher);
}