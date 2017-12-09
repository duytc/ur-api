<?php


namespace UR\Service\DataSet;

use DateTime;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\PublicSimpleException;

class ReloadParams implements ReloadParamsInterface
{
    private $supportedReloadTypes = [ReloadParamsInterface::ALL_DATA_TYPE, ReloadParamsInterface::DETECTED_DATE_RANGE_TYPE, ReloadParamsInterface::IMPORTED_ON_TYPE];

    private $type;
    private $startDate;
    private $endDate;

    /**
     * ReloadParameter constructor.
     * @param $type
     * @param $startDate
     * @param $endDate
     * @throws PublicSimpleException
     */
    public function __construct($type, $startDate, $endDate)
    {
        if (!$this->validateReloadType($type)) {
            throw new PublicSimpleException(sprintf('System does not support reload type = %s', $type));
        }

        if (!$this->validateReloadDate($startDate, $endDate)) {
            throw new PublicSimpleException(sprintf('Invalid reload start date or reload end date value'));
        }

        $this->type = $type;
        $this->startDate = !empty($startDate) ? date_create_from_format(DateFormat::DEFAULT_DATE_FORMAT, $startDate)->setTime(0, 0, 0) : null;
        $this->endDate = !empty($endDate) ? date_create_from_format(DateFormat::DEFAULT_DATE_FORMAT, $endDate)->setTime(0, 0, 0) : null;
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
    }

    /**
     * @inheritdoc
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @inheritdoc
     */
    public function setStartDate($startDate)
    {
        $this->startDate = $startDate;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @inheritdoc
     */
    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * @param $reloadType
     * @return bool
     */
    private function validateReloadType($reloadType)
    {
        if (empty($reloadType)) {
            return false;
        }

        return in_array($reloadType, $this->supportedReloadTypes);
    }

    /**
     * @param $reloadStartDate
     * @param $reloadEndDate
     * @return bool
     */
    private function validateReloadDate($reloadStartDate, $reloadEndDate)
    {
        if (is_null($reloadStartDate) && empty($reloadEndDate)) {
            return true;
        }

        if (!empty($reloadStartDate) && !empty($reloadEndDate)) {
            $reloadStartDate = date_create_from_format('Y-m-d', $reloadStartDate);
            $reloadEndDate = date_create_from_format('Y-m-d', $reloadEndDate);

            if ($reloadStartDate instanceof DateTime && $reloadEndDate instanceof DateTime) {
                return true;
            }
        }

        return false;
    }
}