<?php

namespace UR\Service\Parser;

use UR\Domain\DTO\ConnectedDataSource\DryRunParams;

class DryRunParamsBuilder implements DryRunParamsBuilderInterface
{
    const PAGE_KEY = 'page';
    const LIMIT_KEY = 'limit';
    const SEARCHES = 'searches';
    const ORDER_BY_KEY = 'orderBy';
    const SORT_FIELD_KEY = 'sortField';
    const LIMIT_ROWS = 'limitRows';
    /**
     * @inheritdoc
     */
    public function buildFromArray(array $data)
    {
        $param = new DryRunParams();

        if (array_key_exists(self::ORDER_BY_KEY, $data)) {
            $param->setOrderBy($data[self::ORDER_BY_KEY]);
        }

        if (array_key_exists(self::SORT_FIELD_KEY, $data)) {
            $param->setSortField($data[self::SORT_FIELD_KEY]);
        }

        if (array_key_exists(self::PAGE_KEY, $data)) {
            $param->setPage(intval($data[self::PAGE_KEY]));
        }

        if (array_key_exists(self::LIMIT_KEY, $data)) {
            $param->setLimit(intval($data[self::LIMIT_KEY]));
        }

        if (array_key_exists(self::SEARCHES, $data)) {
            $searches = $data[self::SEARCHES];
            if (is_string($searches)) {
                $searches = json_decode($searches, true);
            }

            $param->setSearches($searches);
        }
        if (array_key_exists(self::LIMIT_ROWS, $data)) {
            $param->setLimitRows(intval($data[self::LIMIT_ROWS]));
        }
        return $param;
    }
}