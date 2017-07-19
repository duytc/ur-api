<?php


namespace UR\Domain\DTO\ConnectedDataSource;


class DryRunParams implements DryRunParamsInterface
{
    /** @var int */
    protected $page;

    /** @var int */
    protected $limit;

    /** @var string */
    protected $orderBy;

    /** @var string */
    protected $sortField;

    /** @var array */
    protected $searches;

    /** @var int */
    protected $limitRows;

    function __construct()
    {
        $this->searches = [];
        $this->limit = 10;
        $this->limitRows = 100;
    }

    /**
     * @return mixed
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param mixed $page
     * @return self
     */
    public function setPage($page)
    {
        $this->page = $page;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @param mixed $limit
     * @return self
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getOrderBy()
    {
        return $this->orderBy;
    }

    /**
     * @param mixed $orderBy
     * @return self
     */
    public function setOrderBy($orderBy)
    {
        $this->orderBy = $orderBy;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSortField()
    {
        return $this->sortField;
    }

    /**
     * @param mixed $sortField
     * @return self
     */
    public function setSortField($sortField)
    {
        $this->sortField = $sortField;
        return $this;
    }

    /**
     * @return array
     */
    public function getSearches()
    {
        return $this->searches;
    }

    /**
     * @param array $searches
     * @return self
     */
    public function setSearches($searches)
    {
        $this->searches = $searches;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getLimitRows()
    {
        return $this->limitRows;
    }

    /**
     * @param mixed $limitRows
     * @return self
     */
    public function setLimitRows($limitRows)
    {
        $this->limitRows = $limitRows;
        return $this;
    }
}