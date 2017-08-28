<?php

namespace UR\Service;

use SplDoublyLinkedList;

trait ArrayUtilTrait
{
    public function getArray(SplDoublyLinkedList $list)
    {
        $data = [];
        foreach ($list as $row) {
            $data[] = $row;
        }

        return $data;
    }

    function isAssoc(array $arr)
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}