<?php


namespace UR\Service\Parser;


use UR\Domain\DTO\ConnectedDataSource\DryRunParamsInterface;

interface DryRunParamsBuilderInterface
{
    /**
     * @param array $params
     * @return DryRunParamsInterface
     */
    public function buildFromArray(array $params);
}