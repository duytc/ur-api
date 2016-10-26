<?php

namespace UR\Service\Parser\Transformer\Collection;

use UR\Service\DTO\Collection;

interface CollectionTransformerInterface
{
    public function transform(Collection $collection);

    /**
     * The idea is that some column transformers should run before others to avoid conflicts
     * i.e usually you would want to group columns before adding calculated fields
     * The parser config should read this priority value and order the transformers based on this value
     * Lower numbers mean higher priority, for example -10 is higher than 0.
     * Maybe we should allow the end user to override this if they know what they are doing
     *
     * @return int
     */
    public function getPriority();
}