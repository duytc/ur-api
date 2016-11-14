<?php


namespace UR\Domain\DTO\Report\Transforms;


use UR\Service\DTO\Collection;

interface TransformInterface
{
    /**
     * @param Collection $collection
     * @return Collection
     */
    public function transform(Collection $collection);
}