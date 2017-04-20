<?php

namespace UR\Service\Metadata;

interface MetadataInterface
{
    const FILE_NAME = '[__filename]';
    /**
     * @param $internalVariable
     * @return mixed|null
     */
    public function getMetadataValueByInternalVariable($internalVariable);
}