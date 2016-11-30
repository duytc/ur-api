<?php
namespace UR\Domain\DTO\Report\Formats;

use UR\Service\DTO\Collection;

interface FormatInterface
{
    const FORMAT_TYPE_KEY = 'type';

    const FORMAT_TYPE_DATE = 'date';
    const FORMAT_TYPE_NUMBER = 'number';
    const FORMAT_TYPE_CURRENCY = 'currency';

    /**
     * @return mixed
     */
    public function getFields();

    /**
     * @param Collection $collection
     * @param array $metrics
     * @param array $dimensions
     * @return mixed
     */
    public function format(Collection $collection,  array $metrics, array $dimensions);
}