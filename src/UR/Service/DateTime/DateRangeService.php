<?php


namespace UR\Service\DateTime;


use DateInterval;
use DatePeriod;
use DateTime;
use Monolog\Logger;
use UR\DomainManager\DataSourceEntryManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Service\DTO\Collection;
use UR\Service\Metadata\Email\EmailMetadata;
use UR\Service\Parser\Transformer\Collection\ExtractPattern;
use UR\Service\Parser\Transformer\Column\DateFormat;
use UR\Service\PublicSimpleException;

class DateRangeService implements DateRangeServiceInterface
{
    const PATTERN_KEY  = 'pattern';
    const REPLACE_VALUE_KEY  = 'value';
    const FILE_NAME_KEY = 'filename';

    /* data source date format config */
    const IS_CUSTOM_FORMAT_DATE = 'isCustomFormatDate';
    const IS_PARTIAL_MATCH = 'isPartialMatch';

    const DYNAMIC_DATE_RANGE_TYPE = 'dynamicDateRange';
    const DYNAMIC_END_DATE_TYPE = 'dynamicEndDate';
    const FIXED_DATE_RANGE_TYPE = 'fixedDateRange';

    const START_DATE_KEY = 'startDate';
    const END_DATE_KEY = 'endDate';
    const DATE_RANGE_TYPE_KEY = 'type';

    /* dynamicDateRange and dynamicEndDate values */
    const THIS_MONTH = 'this month';
    const LAST_MONTH = 'last month';
    const THIS_WEEK = 'this week';
    const LAST_WEEK = 'last week';

    const YESTERDAY = 'yesterday';
    const THREE_DAYS_AGO = '-3 day';
    const SEVEN_DAYS_AGO = '-7 day';
    const THIRTY_DAYS_AGO = '-30 day';


    /** @var  DataSourceEntryManagerInterface */
    protected $dataSourceManager;

    /** @var DataSourceEntryManagerInterface */
    protected $dataSourceEntryManager;

    /** @var DataSourceFileFactory */
    protected $fileFactory;

    /** @var Logger */
    protected $logger;

    /**
     * DateRangeService constructor.
     * @param DataSourceManagerInterface $dataSourceManager
     * @param DataSourceEntryManagerInterface $dataSourceEntryManager
     * @param DataSourceFileFactory $fileFactory
     * @param Logger $logger
     */
    public function __construct(DataSourceManagerInterface $dataSourceManager, DataSourceEntryManagerInterface $dataSourceEntryManager,
                                DataSourceFileFactory $fileFactory, Logger $logger)
    {
        $this->dataSourceManager = $dataSourceManager;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->fileFactory = $fileFactory;
        $this->logger = $logger;
    }

    /**
     * @inheritdoc
     */
    public function calculateDateRangeForDataSource($dataSourceId)
    {
        $dataSource = $this->dataSourceManager->find($dataSourceId);

        if (!$dataSource instanceof DataSourceInterface) {
            return false;
        }

        if (!$dataSource->isDateRangeDetectionEnabled()) {
            return false;
        }

        $dataSourceEntries = $dataSource->getDataSourceEntries();
        if (count($dataSourceEntries) < 1) {
            $dataSource
                ->setMissingDate([])
                ->setDateRangeBroken(false);
            $this->dataSourceManager->save($dataSource);
            return false;
        }

        if (!is_array($dataSourceEntries)) {
            $dataSourceEntries = $dataSourceEntries->toArray();
        }

        usort($dataSourceEntries, function (DataSourceEntryInterface $a, DataSourceEntryInterface $b) {
            return ($a->getStartDate() < $b->getStartDate()) ? -1 : 1;
        });

        $startDate = null;
        $endDate = null;
        $dates = [];

        /** @var DataSourceEntryInterface $dataSourceEntry */
        foreach ($dataSourceEntries as $dataSourceEntry) {
            if (!$dataSourceEntry->getStartDate() instanceof DateTime || !$dataSourceEntry->getEndDate() instanceof DateTime) {
                continue;
            }

            if ($startDate == null || $dataSourceEntry->getStartDate() < $startDate) {
                $startDate = $dataSourceEntry->getStartDate();
            }

            if ($endDate == null || $dataSourceEntry->getEndDate() > $endDate) {
                $endDate = $dataSourceEntry->getEndDate();
            }

            $dates = array_merge($dates, $dataSourceEntry->getDates());
        }

        $dataSource
            ->setDetectedStartDate($startDate)
            ->setDetectedEndDate($endDate);

        $result = $this->getDateRange($dataSource->getDateRange());
        if ($result[self::START_DATE_KEY] instanceof DateTime) {
            $startDate = $result[self::START_DATE_KEY];
        }

        if ($result[self::END_DATE_KEY] instanceof DateTime) {
            $endDate = $result[self::END_DATE_KEY];
        }

        $missingDate = $this->calculateMissingDate($dates, $startDate, $endDate);

        $dataSource->setMissingDate(array_values($missingDate))
            ->setDateRangeBroken(!empty($missingDate));

        $this->dataSourceManager->save($dataSource);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function calculateDateRangeForDataSourceEntry($entryId)
    {
        $dataSourceEntry = $this->dataSourceEntryManager->find($entryId);

        if (!$dataSourceEntry instanceof DataSourceEntryInterface) {
            return false;
        }

        $dataSource = $dataSourceEntry->getDataSource();
        $dateFields = $dataSource->getDateFields();
        $formats = $dataSource->getDateFormats();

        if (empty($dateFields)) {
            throw new PublicSimpleException('"date" field should not be empty');
        }

        if ($dataSource->isFromMetaData()) {
            $detectedDate = null;
            $pattern = $dataSource->getPattern();
            $metaData = $dataSourceEntry->getMetaData();
            foreach ($dateFields as $dateField) {
                if ($dateField == self::FILE_NAME_KEY && (!isset($metaData[$dateField]) || empty($metaData[$dateField]))) {
                    $metaData['filename'] = $dataSourceEntry->getFileName();
                }

                if (!array_key_exists($dateField, $metaData)) {
                    $this->logger->warning(sprintf('"%s" does not exist in the meta data'));
                    continue;
                }

                if (in_array($dateField, [EmailMetadata::META_DATA_EMAIL_DATE, EmailMetadata::INTEGRATION_META_DATA_REPORT_DATE])) {
                    $detectedDate = $this->getDate($metaData[$dateField], $formats);
                    if ($detectedDate instanceof \DateTime) {
                        break;
                    }
                }

                //extract report date based on given pattern
                $collection = new Collection([],[$metaData]);
                $transform = new ExtractPattern($dateField, $pattern[self::PATTERN_KEY], null, $isOverride = true, false, false, $pattern[self::REPLACE_VALUE_KEY]);
                $rows = $transform->transform($collection)->getRows();
                $row = array_shift($rows);
                if (!is_array($row)) {
                    continue;
                }

                $detectedDate = $this->getDate($row[$dateField], $formats);
                if ($detectedDate instanceof \DateTime) {
                    break;
                }
            }

            if ($detectedDate instanceof \DateTime) {
                $dataSourceEntry->setMissingDate([])
                    ->setStartDate($detectedDate)
                    ->setEndDate($detectedDate)
                    ->setDateRangeBroken(false)
                    ->setDates([$detectedDate->format(DateFormat::DEFAULT_DATE_FORMAT)])
                ;

                $this->dataSourceEntryManager->save($dataSourceEntry);
                $this->logger->info(sprintf('Entry %s update %s missing dates', $dataSourceEntry->getId(), count($dataSourceEntry->getMissingDate())));

                $this->calculateDateRangeForDataSource($dataSourceEntry->getDataSource()->getId());
            }

            return true;
        }



        /** Get missing date from parse file */
        try {
            /* parsing data */
            $dataSourceFileData = $this->fileFactory->getFile($dataSource->getFormat(), $dataSourceEntry->getPath());
        } catch (\Exception $e) {
            return false;
        }

        $columns = $dataSourceFileData->getColumns();
        $rows = $dataSourceFileData->getRows();

        try {
            foreach ($rows as &$row) {
                $row = array_combine($columns, $row);
            }
        } catch (\Exception $e) {
            return false;
        }

        $dates = [];

        foreach ($rows as &$row) {
            foreach ($dateFields as $dateField) {
                if (!array_key_exists($dateField, $row)) {
                    continue;
                }

                $value = $this->getDate($row[$dateField], $formats);
                if ($value instanceof DateTime) {
                    $value = $value->format(DateFormat::DEFAULT_DATE_FORMAT);
                    if (!in_array($value, $dates)) {
                        $dates[] = $value;
                    }

                    continue;
                }
            }
        }

        if (empty($dates)) {
            $dataSourceEntry
                ->setStartDate(null)
                ->setEndDate(null)
                ->setDateRangeBroken(null)
                ->setDates($dates);

            return false;
        }

        usort($dates, function ($a, $b) {
            $a = DateTime::createFromFormat(DateFormat::DEFAULT_DATE_FORMAT, $a);
            $b = DateTime::createFromFormat(DateFormat::DEFAULT_DATE_FORMAT, $b);
            return $a < $b ? -1 : 1;
        });


        $dataSourceEntry
            ->setStartDate(new DateTime(reset($dates)))
            ->setEndDate(new DateTime(end($dates)))
            ->setDates($dates);

        $missingDate = $this->calculateMissingDate($dates, $dataSourceEntry->getStartDate(), $dataSourceEntry->getEndDate());
        $dataSourceEntry->setDateRangeBroken(!empty($missingDate));
        $dataSourceEntry->setMissingDate($missingDate);

        $this->dataSourceEntryManager->save($dataSourceEntry);
        $this->logger->info(sprintf('Entry %s update %s missing dates', $dataSourceEntry->getId(), count($dataSourceEntry->getMissingDate())));

        $this->calculateDateRangeForDataSource($dataSourceEntry->getDataSource()->getId());
        return true;
    }

    /**
     * @inheritdoc
     */
    private function calculateMissingDate($keys, DateTime $startDate, DateTime $endDate)
    {
        $start = clone $startDate;
        $end = clone $endDate;

        $period = new DatePeriod(
            $start,
            new DateInterval('P1D'),
            $end->add(new DateInterval('P1D'))
        );

        $dates = iterator_to_array($period);
        $dates = array_map(function (DateTime $date) {
            return $date->format(DateFormat::DEFAULT_DATE_FORMAT);
        }, $dates);

        return array_diff($dates, $keys);
    }

    /**
     * @param $value
     * @param array $formats
     * @return DateTime
     */
    private function getDate($value, $formats)
    {
        $date = null;

        if (!is_array($formats) || empty($formats)) {
            return null;
        }

        /*
         * Parse date by Custom date formats and partial match
         * formats:
         * [
         *     [
         *          format: ..., // YYYY-MM-DD, ...
         *          isCustomDateFormat: true/false,
         *          isPartialMatch: true/false,
         *     ]
         * ]
         */
        foreach ($formats as $formatArray) {
            if (!is_array($formatArray) || !array_key_exists(DateFormat::FORMAT_KEY, $formatArray)) {
                $format = DateFormat::DEFAULT_DATE_FORMAT_FULL;
            } else {
                $format = $formatArray[DateFormat::FORMAT_KEY];
            }

            // support partial match value
            $isPartialMatch = array_key_exists(self::IS_PARTIAL_MATCH, $formatArray) ? $formatArray[self::IS_PARTIAL_MATCH] : false;
            if ($isPartialMatch) {
                $value = DateFormat::getPartialMatchValue($format, $value);
            }

            // support custom date format
            $isCustomDateFormat = array_key_exists(self::IS_CUSTOM_FORMAT_DATE, $formatArray) ? $formatArray[self::IS_CUSTOM_FORMAT_DATE] : false;
            if ($isCustomDateFormat) {
                $format = DateFormat::convertCustomFromDateFormatToPHPDateFormat($format);
            } else {
                $format = DateFormat::convertDateFormatFullToPHPDateFormat($format);
            }

            $date = date_create_from_format($format, $value);
            if ($date instanceof DateTime) {
                break;
            }
        }

        /** Parse date by system support formats */
        if (!$date instanceof DateTime) {
            $date = DateFormat::getDateFromText($value);
        }

        return $date;
    }

    protected function getDateRange($dateRange)
    {
        if (
            !array_key_exists(self::START_DATE_KEY, $dateRange) ||
            !array_key_exists(self::END_DATE_KEY, $dateRange) ||
            !array_key_exists(self::DATE_RANGE_TYPE_KEY, $dateRange)
        ) {
            throw new InvalidArgumentException('Either "startDate" or "endDate" or "type" is missing');
        }

        if ($dateRange[self::DATE_RANGE_TYPE_KEY] == self::DYNAMIC_DATE_RANGE_TYPE) {
            switch ($dateRange[self::START_DATE_KEY]) {
                case self::THIS_WEEK:
                    return array(
                        self::START_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime(self::THIS_WEEK))),
                        self::END_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime('now')))
                    );

                case self::THIS_MONTH:
                    return array(
                        self::START_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-01', strtotime(self::THIS_MONTH))),
                        self::END_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime('now')))
                    );

                case self::LAST_WEEK:
                    return array(
                        self::START_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime(self::LAST_WEEK))),
                        self::END_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime('last sunday')))
                    );

                case self::LAST_MONTH:
                    return array(
                        self::START_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-01', strtotime(self::LAST_MONTH))),
                        self::END_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-t', strtotime(self::LAST_MONTH)))
                    );

                default:
                    throw new InvalidArgumentException(sprintf('dynamic date range "%s" is not supported', $dateRange[self::START_DATE_KEY]));
            }
        }

        if ($dateRange[self::DATE_RANGE_TYPE_KEY] == self::DYNAMIC_END_DATE_TYPE) {
            switch ($dateRange[self::END_DATE_KEY]) {
                case self::YESTERDAY:
                    return array(
                        self::START_DATE_KEY => DateTime::createFromFormat('Y-m-d', $dateRange[self::START_DATE_KEY]),
                        self::END_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime(self::YESTERDAY)))
                    );

                case self::THREE_DAYS_AGO:
                    return array(
                        self::START_DATE_KEY => DateTime::createFromFormat('Y-m-d', $dateRange[self::START_DATE_KEY]),
                        self::END_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime(self::THREE_DAYS_AGO)))
                    );

                case self::SEVEN_DAYS_AGO:
                    return array(
                        self::START_DATE_KEY => DateTime::createFromFormat('Y-m-d', $dateRange[self::START_DATE_KEY]),
                        self::END_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime(self::SEVEN_DAYS_AGO)))
                    );

                case self::THIRTY_DAYS_AGO:
                    return array(
                        self::START_DATE_KEY => DateTime::createFromFormat('Y-m-d', $dateRange[self::START_DATE_KEY]),
                        self::END_DATE_KEY => DateTime::createFromFormat('Y-m-d', date('Y-m-d', strtotime(self::THIRTY_DAYS_AGO)))
                    );

                default:
                    throw new InvalidArgumentException(sprintf('dynamic end date "%s" is not supported', $dateRange[self::END_DATE_KEY]));
            }
        }

        return array(
            self::START_DATE_KEY => DateTime::createFromFormat('Y-m-d', $dateRange[self::START_DATE_KEY]),
            self::END_DATE_KEY => DateTime::createFromFormat('Y-m-d', $dateRange[self::END_DATE_KEY])
        );
    }
}