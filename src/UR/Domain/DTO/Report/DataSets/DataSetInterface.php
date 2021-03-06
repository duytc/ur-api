<?php


namespace UR\Domain\DTO\Report\DataSets;


interface DataSetInterface
{
    /**
     * @return mixed
     */
    public function getDimensions();

    /**
     * @return mixed
     */
    public function getMetrics();

    /**
     * @return mixed
     */
    public function getFilters();

	/**
	 * @param $filters
	 * @return mixed
	 */
	public function setFilters($filters);

    /**
     * @return mixed
     */
    public function getDataSetId();

} 