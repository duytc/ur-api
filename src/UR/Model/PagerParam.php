<?php


namespace UR\Model;


class PagerParam
{
    const PARAM_SEARCH_FIELD = 'searchField';
    const PARAM_SEARCH_KEY = 'searchKey';
    const PARAM_SORT_FIELD = 'sortField';
    const PARAM_SORT_DIRECTION = 'orderBy';
    const PARAM_PUBLISHER_ID = 'publisherId';
    const PARAM_PAGE = 'page';
    const PARAM_LIMIT = 'limit';
    const PARAM_FILTERS = 'filters';

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

    /** @var  int */
    private $page;

    /** @var  int */
    private $limit;

    /**
     * @param string $searchField
     * @param string $searchKey
     * @param string $sortField
     * @param string $sortDirection
     * @param int $publisherId
     * @param int $page
     * @param int $limit
     */
    function __construct($searchField = null, $searchKey = null, $sortField = null, $sortDirection = null, $publisherId, $page = 1, $limit = 10)
    {
        $this->searchField = $searchField;
        $this->searchKey = $searchKey;
        $this->sortField = $sortField;
        $this->sortDirection = $sortDirection;
        $this->publisherId = $publisherId;
        $this->page = $page;
        $this->limit = $limit;
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

    /**
     * @return int
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param int $page
     * @return self
     */
    public function setPage($page)
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     * @return self
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }
}