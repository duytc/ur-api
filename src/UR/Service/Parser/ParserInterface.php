<?php

namespace UR\Service\Parser;

use SplDoublyLinkedList;
use UR\Model\Core\ConnectedDataSourceInterface;

interface ParserInterface
{
    public function parse(array $fileCols, SplDoublyLinkedList $rows, ParserConfig $parserConfig, ConnectedDataSourceInterface $dataSet);
}