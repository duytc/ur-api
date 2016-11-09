<?php


namespace UR\Service\Report;


interface GrouperInterface
{
    /**
     * @param array $transforms
     * @param array $reports
     * @return array
     */
    public function group(array $transforms, array $reports);
}