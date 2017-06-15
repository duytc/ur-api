<?php

namespace UR\Service\Import;


use Psr\Log\LoggerInterface;
use UR\Model\Core\AlertInterface;
use UR\Service\Alert\ConnectedDataSource\ConnectedDataSourceAlertInterface;

class ImportDataLogger
{

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * ImportDataLogger constructor.
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function doImportLogging($errorCode, $dataSetId, $dataSourceId, $dataSourceEntryId, $row, $column)
    {
        $message = $this->getMessageDetails($errorCode, $dataSourceEntryId, $row, $column);

        if ($errorCode == AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORTED_SUCCESSFULLY) {
            $this->logger->info(sprintf("data-set#%s data-source#%s data-source-entry#%s (message: %s)", $dataSetId, $dataSourceId, $dataSourceEntryId, $message));
        } else {
            $this->logger->error(sprintf("data-set#%s data-source#%s data-source-entry#%s (message: %s)", $dataSetId, $dataSourceId, $dataSourceEntryId, $message));
        }
    }

    public function getMessageDetails($errorCode, $dataSourceEntryId, $row, $column)
    {
        switch ($errorCode) {
            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORTED_SUCCESSFULLY:
                $message = sprintf('Data imported successfully');
                break;
            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_MAPPING_FAIL:
                $message = sprintf('failed to import file with id#%s - MAPPING ERROR: no Field is mapped', $dataSourceEntryId);
                break;

            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_WRONG_TYPE_MAPPING:
                $message = sprintf('Failed to import file with id#%s - MAPPING ERROR: wrong type mapping', $dataSourceEntryId);
                break;

            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_REQUIRED_FAIL:
                $message = sprintf('Failed to import file with id#%s - REQUIRE ERROR: require fields%s not exist in file', $dataSourceEntryId, (is_string($column) ? sprintf(' "%s"', $column) : ''));
                break;

            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILTER_ERROR_INVALID_NUMBER:
                $message = sprintf('Failed to import file with id#%s - FILTER ERROR: Wrong number format at row [%s] - column [%s]', $dataSourceEntryId, $row, $column);
                break;

            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILTER_ERROR_INVALID_DATE:
                $message = sprintf('Failed to import file with id#%s - FILTER ERROR: Wrong date format at row [%s] - column [%s]', $dataSourceEntryId, $row, $column);
                break;

            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_TRANSFORM_ERROR_INVALID_DATE:
                $message = sprintf('Failed to import file with id#%s - TRANSFORM ERROR: Wrong date format at row [%s] - column [%s] ', $dataSourceEntryId, $row, $column);
                break;

            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_HEADER_FOUND:
                $message = sprintf('Failed to import file with id#%s - no header found error', $dataSourceEntryId);
                break;

            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_DATA_IMPORT_NO_DATA_ROW_FOUND:
                $message = sprintf('Failed to import file with id#%s - cant find data error', $dataSourceEntryId);
                break;

            case AlertInterface::ALERT_CODE_CONNECTED_DATA_SOURCE_FILE_NOT_FOUND:
                $message = sprintf('Failed to import file with id#%s - file does not exist', $dataSourceEntryId);
                break;
            default:
                $message = sprintf('The import failed, please contact your account manager');
                break;
        }

        return $message;
    }

    public function doLoggingForJson($dataSetId, $dataSourceId, $dataSourceEntryId)
    {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $message = sprintf(' - No errors');
                break;
            case JSON_ERROR_DEPTH:
                $message = sprintf(" - Maximum stack depth exceeded");
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $message = sprintf(" - Underflow or the modes mismatch");
                break;
            case JSON_ERROR_CTRL_CHAR:
                $message = sprintf(" - Unexpected control character found");
                break;
            case JSON_ERROR_SYNTAX:
                $message = sprintf(" - Syntax error, malformed JSON");
                break;
            case JSON_ERROR_UTF8:
                $message = sprintf(" - Malformed UTF-8 characters, possibly incorrectly encoded");
                break;
            default:
                $message = sprintf(" - Unknown error");
                break;
        }

        $this->logger->error(sprintf("Reading json file data-set#%s data-source#%s data-source-entry#%s (message: %s)", $dataSetId, $dataSourceId, $dataSourceEntryId, $message));
    }
}