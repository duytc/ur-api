<?php
namespace UR\Domain\DTO\Report\Formats;

use UR\Service\DTO\Report\ReportResultInterface;

interface FormatInterface
{
    const FORMAT_TYPE_KEY = 'type';

    const FORMAT_TYPE_DATE = 'date';
    const FORMAT_TYPE_NUMBER = 'number';
    const FORMAT_TYPE_CURRENCY = 'currency';
    const FORMAT_TYPE_COLUMN_POSITION = 'columnPosition';

    /* priority for formats, the smaller will be execute first */
    const FORMAT_PRIORITY_DATE = 10;
    const FORMAT_PRIORITY_NUMBER = 10;
    const FORMAT_PRIORITY_CURRENCY = 20; // currency format must be called after number format
    const FORMAT_PRIORITY_COLUMN_POSITION = 30; // arrange column must be called end

    /**
     * @return mixed
     */
    public function getFields();

    /**
     * @return int
     */
    public function getPriority();

    /**
     * @param ReportResultInterface $reportResult
     * @param array $metrics
     * @param array $dimensions
     * @return mixed
     */
    public function format(ReportResultInterface $reportResult, array $metrics, array $dimensions);
}