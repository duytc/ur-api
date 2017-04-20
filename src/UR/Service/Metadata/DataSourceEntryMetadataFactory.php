<?php

namespace UR\Service\Metadata;

use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Metadata\Email\EmailMetadata;

class DataSourceEntryMetadataFactory
{
    /**
     * @param DataSourceEntryInterface $dataSourceEntry
     * @return EmailMetadata
     */
    public function getMetadata(DataSourceEntryInterface $dataSourceEntry)
    {
        $metadata = $dataSourceEntry->getMetaData();
        if (!is_array($metadata) || count($metadata) < 1) {
            $metadata = [];
        }

        return new EmailMetadata($metadata);
    }
}