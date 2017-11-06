<?php

namespace UR\Service\Import;

use UR\Service\DTO\Collection;

interface CsvWriterInterface
{
    /**
     * @param $path
     * @param Collection $collection
     */
    public function insertCollection($path, Collection $collection);
}