<?php


namespace UR\Service\DataSource;


use SplDoublyLinkedList;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use UR\Service\DTO\Report\ReportResult;
use UR\Service\Import\ImportDataException;
use UR\Service\Import\PublicImportDataException;

class DataSourceEntryPreviewService implements DataSourceEntryPreviewServiceInterface
{
    /**
     * @var DataSourceFileFactory
     */
    protected $fileFactory;

    /**
     * @var integer
     */
    protected $maxExcelFileSize;

    /**
     * DataSourceEntryPreviewService constructor.
     * @param DataSourceFileFactory $fileFactory
     * @param $maxExcelFileSize
     */
    public function __construct(DataSourceFileFactory $fileFactory, $maxExcelFileSize)
    {
        $this->fileFactory = $fileFactory;
        $this->maxExcelFileSize = (integer)$maxExcelFileSize;
    }


    /**
     * @inheritdoc
     */
    public function preview(DataSourceEntryInterface $dataSourceEntry, $limit = 100)
    {
        $fileType = $dataSourceEntry->getDataSource()->getFormat();

        /** @var \UR\Service\DataSource\DataSourceInterface $dataSourceFileData */
        $dataSourceFileData = null;

        $chunks = $dataSourceEntry->getChunks();
        $chunkFilePath = $dataSourceEntry->isSeparable() && !empty($chunks) && is_array($chunks)? $this->fileFactory->getAbsolutePath(reset($chunks)) : "";

        if (!empty($chunkFilePath) && is_file($chunkFilePath)) {
            $dataSourceFileData = $this->fileFactory->getFileForChunk($chunkFilePath);
        } else {
            $dataSourceFileData = $this->fileFactory->getFile($fileType, $dataSourceEntry->getPath());
        }
        $columns = $dataSourceFileData->getColumns();

        if (count($columns) < 1) {
            $details = [
                AbstractConnectedDataSourceAlert::CODE => AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_DATA_ROW_FOUND
            ];
            throw new PublicImportDataException($details, new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_DATA_ROW_FOUND));
        }

        $rows = $dataSourceFileData->getLimitedRows($limit);
        $totalRowsCount = $dataSourceEntry->getTotalRow();

        return $this->formatAsReport($columns, $rows, $totalRowsCount);
    }

    /**
     * @param $columns
     * @param $reports
     * @param $count
     * @return array
     */
    private function formatAsReport($columns, SplDoublyLinkedList $reports, $count = 0)
    {
        $types = [];
        $data = [];

        foreach ($reports as $report) {
            foreach ($columns as $key => $value) {
                if (array_key_exists($key, $report)) {
                    $report[$value] = $report[$key];
                    unset($report[$key]);
                }
            }
            $data[] = $report;
        }

        foreach ($columns as $key => $value) {
            $columns[$value] = $value;
            unset($columns[$key]);
            $types[$value] = 'text';
        }

        $dataTransferObject = [];
        $dataTransferObject[ReportResult::REPORT_RESULT_REPORTS] = $data;
        $dataTransferObject[ReportResult::REPORT_RESULT_COLUMNS] = $columns;
        $dataTransferObject[ReportResult::REPORT_RESULT_TOTAL] = $count;
        $dataTransferObject[ReportResult::REPORT_RESULT_AVERAGE] = [];
        $dataTransferObject[ReportResult::REPORT_RESULT_TYPES] = $types;
        $dataTransferObject[ReportResult::REPORT_RESULT_DATE_RANGE] = null;

        unset($reports, $report);
        return $dataTransferObject;
    }
}