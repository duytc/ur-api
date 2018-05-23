<?php


namespace UR\Bundle\ApiBundle\Service\DataSource;

use DateTime;
use Exception;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use UR\Behaviors\FileUtilsTrait;
use UR\DomainManager\DataSourceEntryManager;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Entity\Core\DataSourceEntry;
use UR\Model\Core\AlertInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Alert\DataSource\AbstractDataSourceAlert;
use UR\Service\Alert\DataSource\DataReceivedAlert;
use UR\Service\Alert\DataSource\DataSourceAlertFactory;
use UR\Service\DataSource\DataSourceFileFactory;
use UR\Service\DataSource\DataSourceType;
use UR\Service\Import\ImportDataException;
use UR\Service\Import\ImportService;
use UR\Worker\Manager;


class UploadFileService
{
    use FileUtilsTrait;

    protected $dataSourceManager;

    protected $dataSourceEntryManager;

    /** @var ImportService */
    private $importService;

    /** @var Manager */
    private $workerManager;

    /** @var DataSourceAlertFactory */
    private $alertFactory;

    /** @var DataSourceFileFactory */
    private $dataSourceFileFactory;

    /**
     * @inheritdoc
     */

    /**
     * @param DataSourceManagerInterface $dataSourceManager
     * @param DataSourceEntryManager $dataSourceEntryManager
     * @param ImportService $importService
     * @param Manager $workerManager
     */
    public function __construct(DataSourceManagerInterface $dataSourceManager, DataSourceEntryManager $dataSourceEntryManager, ImportService $importService, Manager $workerManager, DataSourceFileFactory $dataSourceFileFactory)
    {
        $this->dataSourceManager = $dataSourceManager;
        $this->dataSourceEntryManager = $dataSourceEntryManager;
        $this->importService = $importService;
        $this->workerManager = $workerManager;
        $this->alertFactory = new DataSourceAlertFactory();
        $this->dataSourceFileFactory = $dataSourceFileFactory;
    }

    /**
     * @param UploadedFile $file
     * @param $path
     * @param $dirItem
     * @param DataSourceInterface $dataSource
     * @param string $receivedVia
     * @param bool $alsoMoveFile
     * @param null $metadata
     * @param null $startDate
     * @param null $endDate
     * @param bool $removeHistory
     * @return array
     * @throws Exception
     */
    public function uploadDataSourceEntryFile(UploadedFile $file, $path, $dirItem, DataSourceInterface $dataSource, $receivedVia = DataSourceEntry::RECEIVED_VIA_UPLOAD, $alsoMoveFile = true, $metadata = null, $startDate = null, $endDate = null, $removeHistory = false)
    {
        /* validate via type */
        if (!DataSourceEntry::isSupportedReceivedViaType($receivedVia)) {
            throw new \Exception(sprintf("receivedVia %s is not supported", $receivedVia));
        }

        $originName = $file->getClientOriginalName();
        // escape $filename (remove special characters)
        $originName = $this->escapeFileNameContainsSpecialCharacters($originName);

        $filename = basename($originName, '.' . $file->getClientOriginalExtension());

        $publisherId = $dataSource->getPublisher()->getId();

        try {
            // validate file extension before processing upload
            $isExtensionSupport = DataSourceType::isSupportedExtension($file->getClientOriginalExtension());
            if (!$isExtensionSupport) {
                throw new ImportDataException(AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT);
            }

            // automatically update data source format if has no entry before
            // TODO: remove when stable
            //if (count($dataSource->getDataSourceEntries()) < 1) {
            //    $rawFileFormat = $file->getClientOriginalExtension();
            //    $format = DataSourceType::getOriginalDataSourceType($rawFileFormat);
            //    $dataSource->setFormat($format);
            //    $this->dataSourceManager->save($dataSource);
            //}
            /** Cause we will accept all file with the same headers -> no need to check validateUploadFile
             * TODO: ignore this later
             */
//            $isValidExtension = $this->importService->validateUploadedFile($file, $dataSource);
//            if (!$isValidExtension) {
//                throw new ImportDataException(AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD_WRONG_FORMAT);
//            }

            /**
             * Check if have no entry before -> continue
             * else check headers with file before.
             *      -> if they are the same headers -> upload file
             *      -> else -> do not upload file
             *
             */


            // save file to upload dir
            if (DataSourceEntry::RECEIVED_VIA_API === $receivedVia) {
                $name = $originName;
            } else {
                $randomString =   bin2hex(random_bytes(16));
                $name = $filename . '_' . $randomString . '.' . $file->getClientOriginalExtension();
            }

            if (strlen($name) > 230) {
                throw new Exception(sprintf('File Name is too long: name of file should not be more than 230 characters'));
            }

            //if csv file, fix window line feed
            if ($file->getClientOriginalExtension() == DataSourceInterface::CSV_FORMAT) {
                $this->importService->fixCSVLineFeed($file->getRealPath());
            }

            if ($alsoMoveFile) {
                $file->move($path, $name);
            } else {
                if (!file_exists($path)) {
                    mkdir($path, 0755, true);
                }
                copy($file->getRealPath(), $path . '/' . $name);
            }

            $filePath = $path . '/' . $name;

            chmod($filePath, 0664);

            $convertResult = $this->convertToUtf8($filePath, $this->importService->getKernelRootDir());
            if (!$convertResult) {
                throw new \Exception(sprintf("File %s is not valid - cannot convert to UTF-8", $originName));
            }

            $this->dataSourceFileFactory->getFileForChunk($filePath, $dataSource->getSheets());

            // create new data source entry
            $hash = sha1_file($filePath);
            $dataSourceEntry = new DataSourceEntry();
            $dataSourceEntry->setPath($dirItem . '/' . $name)
                ->setIsValid(true)
                ->setReceivedVia($receivedVia)
                ->setFileName($originName)
                ->setHashFile($hash)
                ->setMetaData($metadata)
                ->setDataSource($dataSource)
                ->setFileExtension($file->getClientOriginalExtension())
                ->setRemoveHistory($removeHistory);

            /* Validating startDate and endDate must be not null. */
            if (!empty($startDate) && !empty($endDate)) {
                /* converting startDate and endDate from String to DateTime */
                $startDate = new DateTime($startDate);
                $endDate = new DateTime($endDate);

                $dataSourceEntry
                    ->setStartDate($startDate)
                    ->setEndDate($endDate);
            }

            // persist and flush
            $this->dataSourceEntryManager->save($dataSourceEntry);

            $alert = $this->alertFactory->getAlert(
                AlertInterface::ALERT_CODE_DATA_SOURCE_NEW_DATA_IS_RECEIVED_FROM_UPLOAD,
                $originName,
                $dataSource);

            if ($alert instanceof DataReceivedAlert) {
                $this->workerManager->processAlert($alert->getAlertCode(), $publisherId, $alert->getDetails(), $alert->getDataSourceId());
            }

        } catch (ImportDataException $exception) {
            $code = $exception->getAlertCode();
            $alert = $this->alertFactory->getAlert(
                $code,
                $originName,
                $dataSource);

            if ($alert instanceof AbstractDataSourceAlert) {
                $this->workerManager->processAlert($alert->getAlertCode(), $publisherId, $alert->getDetails(), $alert->getDataSourceId());
            }

            throw new Exception(sprintf('Cannot upload file because the file is in the wrong format.', $originName));
        }

        $result = [
            'file' => $originName,
            'status' => true,
            'message' => sprintf('File %s is uploaded successfully', $originName)
        ];

        return $result;
    }
}
