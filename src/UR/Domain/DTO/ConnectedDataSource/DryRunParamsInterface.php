<?php


namespace UR\Domain\DTO\ConnectedDataSource;


interface DryRunParamsInterface
{
    /**
     * @return mixed
     */
    public function getPage();

    /**
     * @param mixed $page
     * @return self
     */
    public function setPage($page);

    /**
     * @return mixed
     */
    public function getLimit();

    /**
     * @param mixed $limit
     * @return self
     */
    public function setLimit($limit);

    /**
     * @return mixed
     */
    public function getOrderBy();

    /**
     * @param mixed $orderBy
     * @return self
     */
    public function setOrderBy($orderBy);

    /**
     * @return mixed
     */
    public function getSortField();

    /**
     * @param mixed $sortField
     * @return self
     */
    public function setSortField($sortField);

    /**
     * @return array
     */
    public function getSearches();

    /**
     * @param array $searches
     * @return self
     */
    public function setSearches($searches);

    /**
     * @return mixed
     */
    public function getLimitRows();

    /**
     * @param mixed $limitRows
     * @return self
     */
    public function setLimitRows($limitRows);
}