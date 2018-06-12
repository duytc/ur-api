<?php

namespace UR\Service\Metadata;

interface MetadataInterface
{
    const META_DATA_FILE_NAME = 'filename';
    const FILE_NAME = '[__filename]';

    /**
     * @param $internalVariable
     * @return mixed|null
     */
    public function getMetadataValueByInternalVariable($internalVariable);
}