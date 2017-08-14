<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface TagInterface extends ModelInterface
{
    /**
     * @return string|null
     */
    public function getName();

    /**
     * @param string $name
     * @return self
     */
    public function setName($name);

    /**
     * @return mixed
     */
    public function getUserTags();

    /**
     * @param mixed $userTags
     * @return self
     */
    public function setUserTags($userTags);

    /**
     * @return IntegrationTagInterface[]
     */
    public function getIntegrationTags();

    /**
     * @param IntegrationTagInterface[] $integrationTags
     * @return self
     */
    public function setIntegrationTags($integrationTags);

    /**
     * @return ReportViewTemplateTagInterface[]
     */
    public function getReportViewTemplateTags();

    /**
     * @param ReportViewTemplateTagInterface[] $reportViewTemplateTags
     * @return self
     */
    public function setReportViewTemplateTags($reportViewTemplateTags);
}