<?php


namespace UR\Service\ReportViewTemplate\DTO;


interface CustomTemplateParamsInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @param string $name
     * @return self
     */
    public function setName($name);

    /**
     * @return array
     */
    public function getTags();

    /**
     * @param array $tags
     * @return self
     */
    public function setTags($tags);
}