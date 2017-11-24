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
    const AGGREGATION = 'aggregation';
    const POST_AGGREGATION = 'postAggregation';
    const SORT_BY = 'sortBy';
    const ADD_FIELD = 'addField';
    const ADD_FIELD_FROM_DATE = 'addFieldFromDate';
    const ADD_CALCULATED_FIELD = 'addCalculatedField';
    const ADD_CONCATENATED_FIELD = 'addConcatenatedField';
    const COMPARISON = 'comparison';
    const COMPARISON_PERCENT = 'comparisonPercent';
    const REPLACE_TEXT = 'replaceText';
    const EXTRACT_PATTERN = 'extractPattern';
    const AUGMENTATION = 'augmentation';
    const SUBSET_GROUP = 'subsetGroup';
    const CONVERT_CASE = 'convertCase';
    const NORMALIZE_TEXT = 'normalizeText';

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
    const TRANSFORM_PRIORITY_CONVERT_CASE = 70;
    const TRANSFORM_PRIORITY_NORMALIZE_TEXT = 70;

    public function transform(Collection $collection, EntityManagerInterface $em = null, ConnectedDataSourceInterface $connectedDataSource = null, $fromDateFormats = [], $mapFields = []);
}