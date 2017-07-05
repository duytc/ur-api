<?php

namespace UR\Model;

class AlertPagerParam extends PagerParam
{
    const PARAM_FILTER_TYPES = 'types';

    /**
     * @var string
     */
    private $types;

    /**
     * @param string $searchField
     * @param string $searchKey
     * @param string $sortField
     * @param string $sortDirection
     * @param int $publisherId
     * @param null|string $types
     */
    function __construct($searchField = null, $searchKey = null, $sortField = null, $sortDirection = null, $publisherId, $types = null)
    {
        parent::__construct($searchField, $searchKey, $sortField, $sortDirection, $publisherId);

        $this->types = $types;
    }

    /**
     * @return string
     */
    public function getTypes()
    {
        if (is_string($this->types)) {
            return $this->types;
        }

        return '';
    }

    /**
     * @param string $types
     */
    public function setTypes($types)
    {
        $this->types = $types;
    }
}