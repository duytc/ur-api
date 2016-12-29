<?php


namespace UR\Model;


class PagerParam
{
    const PARAM_SEARCH_FIELD = 'searchField';
    const PARAM_SEARCH_KEY = 'searchKey';
    const PARAM_SORT_FIELD = 'sortField';
    const PARAM_SORT_DIRECTION = 'orderBy';
    const PARAM_PUBLISHER_ID = 'publisherId';
    /**
     * @var string
     */
    private $searchField;
    /**
     * @var string
     */
    private $searchKey;
    /**
     * @var string
     */
    private $sortField;
    /**
     * @var string
     */
    private $sortDirection;

    /**
     * @var int
     */
    private $publisherId;

    /**
     * @param string $searchField
     * @param string $searchKey
     * @param string $sortField
     * @param string $sortDirection
     * @param int $publisherId
     */
    function __construct($searchField = null, $searchKey = null, $sortField = null, $sortDirection = null, $publisherId)
    {
        $this->searchField = $searchField;
        $this->searchKey = $searchKey;
        $this->sortField = $sortField;
        $this->sortDirection = $sortDirection;
        $this->publisherId = $publisherId;
    }

    /**
     * @return string
     */
    public function getSearchField()
    {
        if (is_string($this->searchField)) {
            return explode(',', $this->searchField);
        }

        return [];
    }

    /**
     * @param string $searchField
     */
    public function setSearchField($searchField)
    {
        $this->searchField = $searchField;
    }

    /**
     * @return string
     */
    public function getSearchKey()
    {
        return $this->searchKey;
    }

    /**
     * @param string $searchKey
     */
    public function setSearchKey($searchKey)
    {
        $this->searchKey = $searchKey;
    }

    /**
     * @return string
     */
    public function getSortField()
    {
        return $this->sortField;
    }

    /**
     * @param string $sortField
     */
    public function setSortField($sortField)
    {
        $this->sortField = $sortField;
    }

    /**
     * @return string
     */
    public function getSortDirection()
    {
        return $this->sortDirection;
    }

    /**
     * @param string $sortDirection
     */
    public function setSortDirection($sortDirection)
    {
        $this->sortDirection = $sortDirection;
    }

    /**
     * @return int
     */
    public function getPublisherId()
    {
        return $this->publisherId;
    }

    /**
     * @param int $publisherId
     * @return self
     */
    public function setPublisherId($publisherId)
    {
        $this->publisherId = $publisherId;
        return $this;
    }
}