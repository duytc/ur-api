<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface ReportViewTemplateTagInterface extends ModelInterface
{
    /**
     * @return mixed
     */
    public function getId();

    /**
     * @param mixed $id
     */
    public function setId($id);

    /**
     * @return ReportViewTemplateInterface
     */
    public function getReportViewTemplate();

    /**
     * @param ReportViewTemplateInterface $reportViewTemplate
     * @return self
     */
    public function setReportViewTemplate($reportViewTemplate);

    /**
     * @return TagInterface
     */
    public function getTag();

    /**
     * @param TagInterface $tag
     * @return self
     */
    public function setTag($tag);
}