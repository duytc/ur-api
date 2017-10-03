<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;
use UR\Service\Parser\Transformer\TransformerInterface;

interface CollectionTransformerAugmentationInterface extends TransformerInterface
{
    const FIELD_KEY = 'field';

    /* priority for formats, the smaller will be execute first */
    const TRANSFORM_PRIORITY_AUGMENTATION = 1;

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = []);
}