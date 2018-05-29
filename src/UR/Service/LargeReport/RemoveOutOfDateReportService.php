<?php

namespace UR\Service\LargeReport;

use DateTime;

class RemoveOutOfDateReportService implements RemoveOutOfDateReportServiceInterface
{
    protected $reportFileDir;
    protected $expiredTimeSmallFile;
    protected $expiredTimeLargeFile;
    protected $largeFileThreshold;

    /**
     * RemoveOutOfDateReportService constructor.
     * @param $reportFileDir
     * @param $expiredTimeSmallFile
     * @param $expiredTimeLargeFile
     * @param $largeFileThreshold
     */
    public function __construct($reportFileDir, $expiredTimeSmallFile, $expiredTimeLargeFile, $largeFileThreshold)
    {
        $this->reportFileDir = $reportFileDir;
        $this->expiredTimeSmallFile = $expiredTimeSmallFile;
        $this->expiredTimeLargeFile = $expiredTimeLargeFile;
        $this->largeFileThreshold = $largeFileThreshold;
    }

    /**
     * @inheritdoc
     */
    public function removeOutOfDateReport()
    {
        if (!is_dir($this->reportFileDir)) {
            return 0;
        }
        $file_names = scandir($this->reportFileDir);
        $file_names = !empty($file_names) ? array_diff($file_names, ['.', '..']) : [];
        $count = 0;

        foreach ($file_names as $file_name) {
            $file = $this->reportFileDir . '/' . $file_name;
            if (!file_exists($file)) {
                continue;
            }

            if ($this->needRemoveOldFile($file)) {
                @unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param $file
     * @return bool
     */
    private function needRemoveOldFile($file)
    {
        $file_created_time = date('Y-m-d H:i:s', filectime($file));
        $file_created_time = new DateTime($file_created_time, new \DateTimeZone('UTC'));
        $now = new DateTime('now', new \DateTimeZone('UTC'));

        if ((filesize($file) > $this->largeFileThreshold && $now > $file_created_time->modify('+ ' . $this->expiredTimeLargeFile . ' days'))
            || ($now > $file_created_time->modify('+ ' . $this->expiredTimeSmallFile . ' days'))) {
            return true;
        }

        return false;
    }
}