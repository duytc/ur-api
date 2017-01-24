<?php

namespace UR\Model\Core;

use UR\Model\ModelInterface;

interface IntegrationInterface extends ModelInterface
{
    /* type */
    const TYPE_UI = 'ui';
    const TYPE_API = 'api';

    /* method */
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

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
     * @param string $canonicalName
     * @return self
     */
    public function setCanonicalName($canonicalName);

    /**
     * @return string
     */
    public function getCanonicalName();

    /**
     * @return string
     */
    public function getType();

    /**
     * @param string $type
     * @return self
     */
    public function setType($type);

    /**
     * @return array
     */
    public static function supportedTypes();

    /**
     * @return mixed
     */
    public function getMethod();

    /**
     * @param self $method
     */
    public function setMethod($method);

    /**
     * @return array
     */
    public static function supportedMethods();

    /**
     * @return string
     */
    public function getUrl();

    /**
     * @param string $url
     * @return self
     */
    public function setUrl($url);
}