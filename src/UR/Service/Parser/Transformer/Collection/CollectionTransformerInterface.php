<?php

namespace UR\Service\Parser\Transformer\Collection;

use Doctrine\ORM\EntityManagerInterface;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\DTO\Collection;
use UR\Service\Parser\Transformer\TransformerInterface;

interface CollectionTransformerInterface extends TransformerInterface
{
    const TYPE_KEY = 'type';
    const FIELDS_KEY = 'fields';
    const FIELD_KEY = 'field';
    const GROUP_BY = 'groupBy';
    const SORT_BY = 'sortBy';
    const ADD_FIELD = 'addField';
    const ADD_CALCULATED_FIELD = 'addCalculatedField';
    const ADD_CONCATENATED_FIELD = 'addConcatenatedField';
    const COMPARISON = 'comparison';
    const COMPARISON_PERCENT = 'comparisonPercent';
    const REPLACE_TEXT = 'replaceText';
    const EXTRACT_PATTERN = 'extractPattern';
    const AUGMENTATION = 'augmentation';
    const SUBSET_GROUP = 'subset-group';

    /* priority for formats, the smaller will be execute first */
    const TRANSFORM_PRIORITY_AUGMENTATION = 1;
    const TRANSFORM_PRIORITY_SUBSET_GROUP = 2;
    const TRANSFORM_PRIORITY_ADD_CONCATENATION_FIELD = 10;
    const TRANSFORM_PRIORITY_GROUP = 20;
    const TRANSFORM_PRIORITY_ADD_FIELD = 30;
    const TRANSFORM_PRIORITY_ADD_CALCULATED_FIELD = 30;
    const TRANSFORM_PRIORITY_COMPARISON_PERCENT = 30;
    const TRANSFORM_PRIORITY_SORT = 40;
    const TRANSFORM_PRIORITY_REPLACE_TEXT = 50;
    const TRANSFORM_PRIORITY_EXTRACT_PATTERN = 60;

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null);

    /**
     * The idea is that some column transformers should run before others to avoid conflicts
     * i.e usually you would want to group columns before adding calculated fields
     * The parser config should read this priority value and order the transformers based on this value
     * Lower numbers mean higher priority, for example -10 is higher than 0.
     * Maybe we should allow the end user to override this if they know what they are doing
     *
     * @return int
     */
    public function getDefaultPriority();
}