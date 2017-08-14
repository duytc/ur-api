<?php


namespace UR\Service\ReportViewTemplate\DTO;

class CustomTemplateParams implements CustomTemplateParamsInterface
{
    /** @var  string */
    protected $name;

    /** @var  array */
    protected $tags;

    /**
     * CustomTemplateParams constructor.
     */
    public function __construct()
    {
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * @inheritdoc
     */
    public function setTags($tags)
    {
        $this->tags = $tags;

        return $this;
    }
}