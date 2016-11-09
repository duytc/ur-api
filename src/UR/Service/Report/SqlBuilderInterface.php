<?php


namespace UR\Service\Report;


use Doctrine\DBAL\Schema\Table;

interface SqlBuilderInterface
{
    /**
     * @param Table $table
     * @param array $fields
     * @param array $filters
     * @return string
     */
    public function buildSelectQuery(Table $table, array $fields, array $filters);
}