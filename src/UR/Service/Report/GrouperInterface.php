<?php


namespace UR\Service\Report;


use UR\Domain\DTO\Report\ParamsInterface;

interface GrouperInterface
{
    /**
     * @param ParamsInterface $params
     * @param array $reports
     * @return array
     */
    public function group(ParamsInterface $params, array $reports);
}