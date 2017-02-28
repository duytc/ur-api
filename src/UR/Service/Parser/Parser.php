<?php

namespace UR\Service\Parser;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PostLoadDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreTransformCollectionDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreTransformColumnDataEvent;
use UR\Bundle\ApiBundle\Event\CustomCodeParse\PreFilterDataEvent;
use UR\Bundle\ApiBundle\Event\UrGenericEvent;
use UR\Model\Core\ConnectedDataSourceInterface;
use UR\Service\Alert\ProcessAlert;
use UR\Service\DataSet\Type;
use UR\Service\DataSource\DataSourceInterface;
use UR\Service\DTO\Collection;
use UR\Service\Import\ImportDataException;
use UR\Service\Parser\Filter\ColumnFilterInterface;
use UR\Service\Parser\Transformer\Column\ColumnTransformerInterface;
use UR\Service\Parser\Transformer\Collection\CollectionTransformerInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;

class Parser implements ParserInterface
{
    private $numberSpecialCharacters = ["n/a", "-"];

    /**@var EventDispatcherInterface $eventDispatcher
     */
    protected $eventDispatcher;

    /**
     * Parser constructor.
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function parse(DataSourceInterface $dataSource, ParserConfig $parserConfig, ConnectedDataSourceInterface $connectedDataSource)
    {
        $columnsMapping = $parserConfig->getAllColumnMappings();
        $columnFromMap = array_flip($parserConfig->getAllColumnMappings());

        $fileCols = array_map("strtolower", $dataSource->getColumns());
        $fileCols = array_map("trim", $fileCols);

        $columns = array_intersect($fileCols, $parserConfig->getAllColumnMappings());
        $columns = array_map(function ($fromColumn) use ($columnFromMap) {
            return $columnFromMap[$fromColumn];
        }, $columns);

        $format = [];//format date
        foreach ($parserConfig->getColumnTransforms() as $field => $columnTransform) {
            foreach ($columnTransform as $item) {
                if ($item instanceof DateFormat) {
                    $format[$field] = $item->getFromDateFormat();
                }
            }
        }

        /* 1. get all row data */
        $rows = array_values($dataSource->getRows($format));

        // dispatch event after loading data
        $postLoadDataEvent = new UrGenericEvent(
            new PostLoadDataEvent(
                $connectedDataSource->getDataSet()->getPublisherId(),
                $connectedDataSource->getId(),
                $connectedDataSource->getDataSource()->getId(),
                null,
                null,
                $rows,
                null,
                null
            )
        );
        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_POST_LOADED_DATA,
            $postLoadDataEvent
        );
        $postLoadDataEventResult = $postLoadDataEvent->getArguments();
        if (is_array($postLoadDataEventResult) && array_key_exists('row', $postLoadDataEventResult)) {
            $rows = $postLoadDataEventResult['rows'];
        }

        $dataSetColumns = array_merge($connectedDataSource->getDataSet()->getDimensions(), $connectedDataSource->getDataSet()->getMetrics());

        /* 2. do filtering data */
        // dispatch event pre filtering data
        $preFilterEvent = new UrGenericEvent(
            new PreFilterDataEvent(
                $connectedDataSource->getDataSet()->getPublisherId(),
                $connectedDataSource->getId(),
                $connectedDataSource->getDataSource()->getId(),
                null,
                null,
                $rows,
                null,
                null
            )
        );
        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_PRE_FILTER_DATA,
            $preFilterEvent
        );
        $preFilterEventResult = $preFilterEvent->getArguments();
        if (is_array($preFilterEventResult) && array_key_exists('row', $preFilterEventResult)) {
            $rows = $preFilterEventResult['rows'];
        }

        $cur_row = -1;
        foreach ($rows as &$row) {
            $cur_row++;
            $isMapped = count(array_diff_key(array_flip($fileCols), $row));
            if ($isMapped > 0) {
                $row = array_combine($fileCols, $row);
            }

            foreach ($dataSetColumns as $dsColumn => $type) {
                if (!array_key_exists($dsColumn, $columnsMapping)) {
                    continue;
                }

                if (!array_key_exists($columnsMapping[$dsColumn], $row)) {
                    continue;
                }

                if (strcmp($row[$columnsMapping[$dsColumn]], "") === 0) {
                    $row[$columnsMapping[$dsColumn]] = null;
                }

                if ($row[$columnsMapping[$dsColumn]] !== null) {
                    if (strcmp($type, Type::NUMBER) === 0 || strcmp($type, Type::DECIMAL) === 0) {
                        $row[$columnsMapping[$dsColumn]] = str_replace("$", "", $row[$columnsMapping[$dsColumn]]);
                        $row[$columnsMapping[$dsColumn]] = str_replace("%", "", $row[$columnsMapping[$dsColumn]]);
                        $row[$columnsMapping[$dsColumn]] = str_replace(",", "", $row[$columnsMapping[$dsColumn]]);
                        if (strcmp($type, Type::DECIMAL) === 0) {
                            $row[$columnsMapping[$dsColumn]] = str_replace(" ", "", $row[$columnsMapping[$dsColumn]]);
                        }

                        if (in_array($row[$columnsMapping[$dsColumn]], $this->numberSpecialCharacters)) {
                            $row[$columnsMapping[$dsColumn]] = null;
                        }

                        if (!is_numeric($row[$columnsMapping[$dsColumn]]) && $row[$columnsMapping[$dsColumn]] !== null) {
                            throw new ImportDataException(ProcessAlert::ALERT_CODE_WRONG_TYPE_MAPPING, $cur_row + 2, $columnsMapping[$dsColumn]);
                        }
                    }
                }
            }

            $isValidFilter = 1;
            foreach ($parserConfig->getColumnFilters() as $column => $filters) {
                /**@var ColumnFilterInterface[] $filters */
                if (!in_array($column, $fileCols)) {
                    continue;
                }

                foreach ($filters as $filter) {
                    $filterResult = $filter->filter($row[$column]);
                    if ($filterResult > 1) {
                        throw new ImportDataException($filterResult, $cur_row + 2, $column);
                    } else {
                        $isValidFilter = $isValidFilter & $filterResult;
                    }
                }
            }

            if (!$isValidFilter) {
                unset($rows[$cur_row]);
                continue;
            }
        }

        // TODO: may dispatch event after filtering data

        $collection = new Collection($columns, $rows);

        if (count($rows) < 1) {
            return $collection;
        }

        /* 3. do transforming data */
        $allFieldsTransforms = $parserConfig->getCollectionTransforms();

        if (!$parserConfig->isUserReorderTransformsAllowed()) {
            usort($allFieldsTransforms, function (CollectionTransformerInterface $a, CollectionTransformerInterface $b) {
                if ($a->getDefaultPriority() == $b->getDefaultPriority()) {
                    return 0;
                }
                return ($a->getDefaultPriority() < $b->getDefaultPriority()) ? -1 : 1;
            });
        } else {
            usort($allFieldsTransforms, function (CollectionTransformerInterface $a, CollectionTransformerInterface $b) {
                if ($a->getPriority() == $b->getPriority()) {
                    return 0;
                }
                return ($a->getPriority() < $b->getPriority()) ? -1 : 1;
            });
        }

        // dispatch event pre transforming collection data
        $preTransformCollectionEvent = new UrGenericEvent(
            new PreTransformCollectionDataEvent(
                $connectedDataSource->getDataSet()->getPublisherId(),
                $connectedDataSource->getId(),
                $connectedDataSource->getDataSource()->getId(),
                null,
                null,
                $collection,
                null,
                null
            )
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_PRE_TRANSFORM_COLLECTION_DATA,
            $preTransformCollectionEvent
        );

        $preTransformCollectionEvent = $preFilterEvent->getArguments();
        if (is_array($preTransformCollectionEvent) && array_key_exists('collection', $preTransformCollectionEvent)) {
            $collection = $preTransformCollectionEvent['collection'];
        }

        // transform collection
        foreach ($allFieldsTransforms as $transform) {
            /** @var CollectionTransformerInterface $transform */
            try {
                $collection = $transform->transform($collection);
            } catch (\Exception $e) {
                return $collection;
            }
        }

        // dispatch event pre transforming column data
        $preTransformColumnEvent = new UrGenericEvent(
            new PreTransformColumnDataEvent(
                $connectedDataSource->getDataSet()->getPublisherId(),
                $connectedDataSource->getId(),
                $connectedDataSource->getDataSource()->getId(),
                null,
                null,
                $collection,
                null,
                null
            )
        );

        $this->eventDispatcher->dispatch(
            self::EVENT_NAME_PRE_TRANSFORM_COLUMN_DATA,
            $preTransformColumnEvent
        );
        $preTransformColumnEvent = $preFilterEvent->getArguments();
        if (is_array($preTransformColumnEvent) && array_key_exists('collection', $preTransformColumnEvent)) {
            $collection = $preTransformColumnEvent['collection'];
        }

        // transform column
        $rows = $collection->getRows();
        $cur_row = -1;
        foreach ($rows as &$row) {
            $cur_row++;
            foreach ($parserConfig->getColumnTransforms() as $column => $transforms) {
                /** @var ColumnTransformerInterface[] $transforms */
                if (!array_key_exists($columnsMapping[$column], $row)) {
                    continue;
                }

                foreach ($transforms as $transform) {
                    $row[$columnsMapping[$column]] = $transform->transform($row[$columnsMapping[$column]]);
                    if ($row[$columnsMapping[$column]] === 2) {
                        throw new ImportDataException(ProcessAlert::ALERT_CODE_TRANSFORM_ERROR_INVALID_DATE, $cur_row + 2, $columnsMapping[$column]);
                    }
                }
            }
        }

        $collection->setRows($rows);

        return $collection;
    }
}