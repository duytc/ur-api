<?php

namespace UR\Service\AutoOptimization\DTO;

use UR\Service\DTO\Collection;

interface IdentifierGeneratorInterface
{
    /**
     * @param Collection $collection
     * @return Collection
     */
    public function generateIdentifiers(Collection $collection);
}