<?php

namespace UR\Service\Parser;

use SplDoublyLinkedList;
use UR\Model\Core\ConnectedDataSourceInterface;

interface ParserInterface
{
    const TYPE = 'parserType';
    const TYPE_PRE_GROUPS = 'preGroups';
    const TYPE_GROUPS = 'groups';
    const TYPE_POST_GROUPS = 'postGroups';
    const TYPE_DEFAULT = 'all';
    const NO_GROUP_TRANSFORMS = -1;

    /**
     * @param array $fileCols
     * @param SplDoublyLinkedList $rows
     * @param ParserConfig $parserConfig
     * @param ConnectedDataSourceInterface $dataSet
     * @param string $parserType
     * @return mixed
     */
    public function parse(array $fileCols, SplDoublyLinkedList $rows, ParserConfig $parserConfig, ConnectedDataSourceInterface $dataSet, $parserType = ParserInterface::TYPE_DEFAULT);
}