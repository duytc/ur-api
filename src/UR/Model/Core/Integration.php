<?php

namespace UR\Model\Core;

use UR\Service\StringUtilTrait;

class Integration implements IntegrationInterface
{
    use StringUtilTrait;

    protected $id;
    protected $name;
    protected $canonicalName;
    protected $params;

    public function __construct()
    {
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName($name)
    {
        $this->name = $name;
        $this->canonicalName = $this->normalizeName($name);

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function setCanonicalName($canonicalName)
    {
        $this->canonicalName = $canonicalName;
    }

    /**
     * @inheritdoc
     */
    public function getCanonicalName()
    {
        return $this->canonicalName;
    }

    /**
     * @inheritdoc
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * @inheritdoc
     */
    public function setParams($params)
    {
        $this->params = $params;

        return $this;
    }
}