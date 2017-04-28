<?php


namespace UR\Service\DataSource;


use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSourceEntryInterface;
use UR\Service\Alert\ConnectedDataSource\AbstractConnectedDataSourceAlert;
use UR\Service\Alert\ConnectedDataSource\ImportFailureAlert;
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
//        if ($fileType == DataSourceType::DS_EXCEL_FORMAT && filesize($filePath) >= $this->maxExcelFileSize){
//            throw new \Exception(sprintf('We allow preview Excel file with size below %s MB', (int) $this->maxExcelFileSize/1000000));
//        }

        /** @var \UR\Service\DataSource\DataSourceInterface $dataSourceFileData */
        $dataSourceFileData = $this->fileFactory->getFile($fileType, $dataSourceEntry->getPath());
        $columns = $dataSourceFileData->getColumns();

        if (count($columns) < 1) {
            $details = [
                AbstractConnectedDataSourceAlert::CODE => AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_DATA_ROW_FOUND
            ];
            throw new PublicImportDataException($details, new ImportDataException(AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_DATA_ROW_FOUND));
        }

        $allRows = $dataSourceFileData->getLimitedRows($limit);
        $totalRowsCount = $dataSourceFileData->getTotalRows();

        return $this->formatAsReport($columns, array_values($allRows), $totalRowsCount);
    }

    /**
     * @param $columns
     * @param $reports
     * @param $count
     * @return array
     */
    private function formatAsReport($columns, $reports, $count = 0)
    {
        $types = [];

        if (is_array($reports)) {
            foreach ($reports as &$report) {
                foreach ($columns as $key => $value) {
                    if (array_key_exists($key, $report)) {
                        $report[$value] = $report[$key];
                        unset($report[$key]);
                    }
                }
            }
        }

        foreach ($columns as $key => $value) {
            $columns[$value] = $value;
            unset($columns[$key]);
            $types[$value] = 'text';
        }

        $dataTransferObject = [];
        $dataTransferObject[ReportResult::REPORT_RESULT_REPORTS] = $reports;
        $dataTransferObject[ReportResult::REPORT_RESULT_COLUMNS] = $columns;
        $dataTransferObject[ReportResult::REPORT_RESULT_TOTAL] = $count;
        $dataTransferObject[ReportResult::REPORT_RESULT_AVERAGE] = [];
        $dataTransferObject[ReportResult::REPORT_RESULT_TYPES] = $types;
        $dataTransferObject[ReportResult::REPORT_RESULT_DATE_RANGE] = null;

        return $dataTransferObject;
    }
}