<?php


namespace UR\Domain\DTO\Report\Filters;

use DateTime;

interface DateFilterInterface extends FilterInterface
{
    /**
     * @return string
     */
    public function getDateFormat();
    /**
     * @return DateTime
     */
    public function getEndDate();

    /**
     * @return DateTime
     */
    public function getStartDate();

	/**
	 * @param string $dateFormat
	 */
	public function setDateFormat(string $dateFormat);

	/**
	 * @param string $dateType
	 */
	public function setDateType(string $dateType);

	/**
	 * @param array|string $dateValue
	 */
	public function setDateValue($dateValue);

    /**
     * @return string
     */
    public function getDateType();

	/**
	 * @return boolean
	 */
	public function isUserDefine();

	/**
	 * @param boolean $userDefine
	 * @return self
	 */
	public function setUserDefine($userDefine);
}