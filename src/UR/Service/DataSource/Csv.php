<?php

namespace UR\Service\DataSource;

use League\Csv\AbstractCsv;
use League\Csv\Reader;
use SplDoublyLinkedList;
use UR\Behaviors\ParserUtilTrait;
use UR\Exception\InvalidArgumentException;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\DTO\Collection;
use UR\Service\Import\CsvWriter;

class Csv extends CommonDataSourceFile implements DataSourceInterface
{
    use ParserUtilTrait;

    const DELIMITER_COMMA = ",";
    const DELIMITER_TAB = "\t"; // important: using double quote instead of single quote for special characters!!!

    public static $SUPPORTED_DELIMITERS = [self::DELIMITER_COMMA, self::DELIMITER_TAB];

    /**
     * @var AbstractCsv
     */
    protected $csv;

    /**
     * @var array
     */
    protected $delimiters;

    /**
     * @var array
     */
    protected $headers;
    protected $headerRow = 0;
    protected $dataRow = 0;

    /**
     * Csv constructor.
     * @param string $filePath
     * @param string|array if null, $delimiters default is self::$SUPPORTED_DELIMITERS
     */
    public function __construct($filePath, $delimiters = null)
    {
        // todo validate $filePath
        $this->csv = Reader::createFromPath($filePath, 'r');

        $delimiters = null === $delimiters ? self::$SUPPORTED_DELIMITERS : $delimiters;
        $this->setDelimiters($delimiters);
        $this->detectColumns();
    }

    /**
     * @param array $delimiters
     * @return bool
     */
    public static function isSupportedDelimiters(array $delimiters)
    {
        foreach ($delimiters as $delimiter) {
            if (!in_array($delimiter, self::$SUPPORTED_DELIMITERS)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string|array $delimiters
     * @return $this
     */
    public function setDelimiters($delimiters)
    {
        $delimiters = is_array($delimiters) ? $delimiters : [$delimiters];
        if (!self::isSupportedDelimiters($delimiters)) {
            throw new InvalidArgumentException(sprintf('Not supported delimiters %s for this csv file', implode(',', $delimiters)));
        }

        $this->delimiters = $delimiters;

        return $this;
    }

    /**
     * @param $headerRow
     * @return $this
     */
    public function setHeaderRow($headerRow)
    {
        $this->headerRow = $headerRow;

        return $this;
    }

    /**
     * @param array $headers
     * @return $this
     */
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
        $this->setDataRow(0);

        return $this;
    }

    /**
     * @param $dataRow
     * @return $this
     */
    public function setDataRow($dataRow)
    {
        $this->dataRow = $dataRow;

        return $this;
    }

    /**
     * @return array
     */
    public function getColumns()
    {
        if (is_array($this->headers)) {
            return $this->convertEncodingToASCII($this->headers);
        }

        return [];
    }

    public function detectColumns()
    {
        if (is_array($this->headers)) {
            return $this->convertEncodingToASCII($this->headers);
        }

        $all_rows = [];
        $i = 0;

        // try fetchAll csv with current delimiters
        $validDelimiter = false;
        foreach ($this->delimiters as $delimiter) {
            try {
                $this->csv->setDelimiter($delimiter);
                $this->csv->setLimit(500);
                $this->csv->stripBom(true);
                $all_rows = $this->csv->fetchAll();

                $numOfRows = count($all_rows);
                if (is_array($all_rows) && count($all_rows) > 0) {
                    for ($x = 0; $x < self::DETECT_HEADER_ROWS; $x++) {
                        if ($x >= $numOfRows) {
                            break;
                        }
                        // check the 20 first rows is array and has at least 2 columns
                        $firstRow = $all_rows[$x];
                        if (is_array($firstRow) && count($firstRow) > 1) {
                            // found, so quit the loop
                            $validDelimiter = $delimiter;
                            break;
                        }
                    }
                }

                if ($validDelimiter) {
                    break;
                }
            } catch (\Exception $e) {
                // not support delimiter or other reason, then we try with other delimiters
            }
        }

        // could not parse due to not supported delimiters or other exception
        if (false === $validDelimiter) {
            if (!is_array($this->headers)) {
                $this->csv->setDelimiter(self::DELIMITER_COMMA);
            }
        }

        for ($row = 0; $row < count($all_rows); $row++) {
            $cur_row = $this->removeInvalidColumns($all_rows[$row]);

            if (count($cur_row) < 1) {
                continue;
            }

            $i++;

            if ($this->isTextArray($cur_row) && !$this->isEmptyArray($cur_row)) {
                $this->headers = $cur_row;
                $this->headerRow = $row;
                break;
            }

            if ($i >= DataSourceInterface::DETECT_HEADER_ROWS) {
                break;
            }
        }

        $this->dataRow = $this->headerRow + 1;

        if ($this->headers === null) {
            return [];
        }

    }

    /**
     * @return SplDoublyLinkedList
     */
    public function getRows()
    {
        $iterator = $this->csv->getIterator();
        $result = new SplDoublyLinkedList();
        $index = 0;
        while (!$iterator->eof()) {
            if ($index < $this->dataRow) {
                $index++;
                $iterator->next();
                continue;
            }
            $row = $iterator->current();
            if (!is_array($row) || $this->isEmptyArray($row)) {
                $index++;
                $iterator->next();
                continue;
            }

            $result->push($this->removeNonUtf8CharactersForSingleRow($row));
            $iterator->next();
        }

        //remove header
        $result->shift();
        return $result;
    }

    /**
     * @param $limit
     * @return SplDoublyLinkedList
     */
    public function getLimitedRows($limit = 100)
    {
        if (!is_numeric($limit)) {
            return $this->getRows();
        }

        $iterator = $this->csv->getIterator();
        $result = new SplDoublyLinkedList();
        $index = 0;
        while (!$iterator->eof()) {
            if ($index < $this->dataRow) {
                $index++;
                $iterator->next();
                continue;
            }
            $row = $iterator->current();
            if (!is_array($row) || $this->isEmptyArray($row)) {
                $index++;
                $iterator->next();
                continue;
            }

            $result->push($this->removeNonUtf8CharactersForSingleRow($row));
            $index++;
            if ($index > $limit) {
                break;
            }
            $iterator->next();
        }

        //remove header
        $result->shift();
        return $result;
    }

    /**
     * @inheritdoc
     */
    public function getTotalRows()
    {
        return count($this->getRows());
    }

    /**
     * @return int
     */
    public function getDataRow()
    {
        return $this->dataRow;
    }

    /**
     * @return array
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get body rows of CSV file
     */
    public function getBodyRows()
    {
        $rowValues = [];
        $dll =  $this->getRows();
        $dll->rewind();

        while($dll->valid()){
            $rowValues[] = $dll->current();
            $dll->next();
        }

        return $rowValues;
    }

    public function splitHugeFile(DataSourceEntryInterface $dataSourceEntry, $chunkFilePath, $uploadFileDir, $numberOfRowToExport) {
        $numberOfRows = !empty($numberOfRowToExport) && is_int($numberOfRowToExport) ? $numberOfRowToExport : 2000;
        $writer = new CsvWriter();
        $header = $this->getHeaders();
        $rows = new SplDoublyLinkedList();

        $rowCount = 0;  //count number of rows
        $smallFileCount = 1; // count number of file is splitted
        $index = 0;

        $chunks = [];
        gc_enable();

        $iterator = $this->csv->getIterator();

        while (!$iterator->eof()) {
            if ($index < $this->dataRow) {
                $index++;
                $iterator->next();
                continue;
            }

            $row = $iterator->current();
            if (!is_array($row) || $this->isEmptyArray($row)) {
                $index++;
                $iterator->next();
                continue;
            }

            $rows->push($this->removeNonUtf8CharactersForSingleRow($row));
            $iterator->next();
            $rowCount++;

            if ($rowCount % $numberOfRows == 0) {
                $collection = new Collection($header, $rows);
                $outputFileName = sprintf('%s/subFile-%d.%s', $this->getSourceDirName($chunkFilePath), $smallFileCount, "csv");
                $writer->insertCollection($outputFileName, $collection);

                $smallFileCount++;
                $outputFileNameToSave = str_replace($uploadFileDir, '', $outputFileName);
                $chunks[] = $outputFileNameToSave;

                // reset $newRows
                $newRows = new SplDoublyLinkedList();
                gc_collect_cycles();
            }
        }

        // save
        if (!empty($newRows) && is_array($newRows)) {
            $collection = new Collection($header, $rows);

            $outputFileName = sprintf('%s/subFile-%d.%s', $this->getSourceDirName($chunkFilePath), $smallFileCount, "csv");
            $writer->insertCollection($outputFileName, $collection);

            $outputFileNameToSave = str_replace($uploadFileDir, '', $outputFileName);
            $chunks[] = $outputFileNameToSave;

            // reset $newRows
            $newRows = new SplDoublyLinkedList();
            gc_collect_cycles();
        }

        $dataSourceEntry->setSeparable(true);
        $dataSourceEntry->setChunks($chunks);
        $dataSourceEntry->setTotalRow($rowCount);

        return $dataSourceEntry;
    }

    /**
     * @param $filePath
     * @return mixed
     */
    private function getSourceDirName($filePath)
    {
        $sourceDir =  pathinfo($filePath, PATHINFO_DIRNAME);

        return $sourceDir;
    }
}