<?php

namespace UR\Model\Core;

use UR\Service\StringUtilTrait;

class Integration implements IntegrationInterface
{
    use StringUtilTrait;

    protected $id;
    protected $name;
    protected $canonicalName;
    protected $type;
    protected $method;
    protected $url;

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
    public function getType()
    {
        return $this->type;
    }

    /**
     * @inheritdoc
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function supportedTypes()
    {
        return [
            self::TYPE_UI,
            self::TYPE_API
        ];
    }

    /**
     * @inheritdoc
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @inheritdoc
     */
    public function setMethod($method)
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public static function supportedMethods()
    {
        return [
            self::METHOD_GET,
            self::METHOD_POST
        ];
    }

    /**
     * @inheritdoc
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @inheritdoc
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }
}