<?php

namespace UR\Bundle\AppBundle\Command;

use Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSourceIntegrationManagerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceIntegration;
use UR\Model\Core\DataSourceIntegrationInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Alert\DataSource\DataSourceAlertInterface;

class ConvertTimeZoneCheckAtToUTCCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:migrate:checkat-timezone';
    const KEEP_TIMEZONES = [
        'UTC' => 'UTC',
        'EST5EDT' => 'EST5EDT',
        'CST6CDT' => 'CST6CDT',
        'PST8PDT' => 'PST8PDT',
    ];

    const CONVERT_TIMEZONES = [
        'EST' => 'EST5EDT',
        'CST' => 'CST6CDT',
        'PST' => 'PST8PDT',
        'OTHERS' => 'UTC',
    ];

    /** @var  Logger */
    private $logger;

    /** @var  DataSourceIntegrationManagerInterface */
    private $dataSourceIntegrationManager;

    /** @var  DataSourceManagerInterface */
    private $dataSourceManager;

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('command to migrate old timezones that are not in UTC, EST5EDT, CST6CDT, PST8PST to UTC as by default');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();

        $this->logger = $container->get('logger');
        $this->dataSourceIntegrationManager = $container->get('ur.domain_manager.data_source_integration');
        $this->dataSourceManager = $container->get('ur.domain_manager.data_source');


        $this->logger->info('starting command...');

        $dataSourceIntegrations = $this->dataSourceIntegrationManager->all();
        $migrateDataSourceIntegrationCount = 0;
        foreach ($dataSourceIntegrations as $dataSourceIntegration) {
            if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
                continue;
            }
            if ($this->migrateTimeZoneCheckAtForDataSourceIntegration($dataSourceIntegration)) {
                $migrateDataSourceIntegrationCount++;
            }
        }

        $dataSources = $this->dataSourceManager->all();
        $migrateDataSourceCount = 0;
        foreach ($dataSources as $dataSource) {
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }
            if ($this->migrateTimeZoneAlertSettingForDataSource($dataSource)) {
                $migrateDataSourceCount++;
            }
        }

        $this->logger->info('command run successfully with ' . $migrateDataSourceIntegrationCount . ' data source integrations, and ' . $migrateDataSourceCount . ' data sources');
    }

    /**
     * @param DataSourceIntegrationInterface $dataSourceIntegration
     * @return bool
     */
    private function migrateTimeZoneCheckAtForDataSourceIntegration($dataSourceIntegration)
    {
        if (!$dataSourceIntegration instanceof DataSourceIntegrationInterface) {
            return false;
        }

        $schedule = $dataSourceIntegration->getSchedule();

        if (!is_array($schedule)) {
            return false;
        }

        if (!array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECKED, $schedule)) {
            return false;
        }

        if ($schedule[DataSourceIntegration::SCHEDULE_KEY_CHECKED] != DataSourceIntegration::SCHEDULE_KEY_CHECK_AT) {
            return false;
        }

        $checkAts = $schedule[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT];

        $isChanged = false;

        foreach ($checkAts as &$checkAt) {
            if (!array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_TIME_ZONE, $checkAt)) {
                continue;
            }

            $currentTimeZone = $checkAt[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_TIME_ZONE];
            if (in_array($currentTimeZone, self::KEEP_TIMEZONES)) {
                continue;
            }

            if (array_key_exists($currentTimeZone, self::CONVERT_TIMEZONES)) {
                $convertTimeZone = new \DateTimeZone(self::CONVERT_TIMEZONES[$currentTimeZone]);
            } else {
                $convertTimeZone = new \DateTimeZone('UTC');
            }

            if (array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_HOUR, $checkAt)) {
                $hour = $checkAt[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_HOUR];
            } else {
                $hour = 1;
            }

            if (array_key_exists(DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_HOUR, $checkAt)) {
                $minutes = $checkAt[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_MINUTES];
            } else {
                $minutes = 0;
            }

            $isChanged = true;
            $convertTime = new \DateTime('now', new \DateTimeZone($currentTimeZone));
            $convertTime->setTime($hour, $minutes);
            $convertTime->setTimezone($convertTimeZone);

            $checkAt[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_TIME_ZONE] = $convertTimeZone->getName();
            $checkAt[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_HOUR] = (int)$convertTime->format('H');
            $checkAt[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT_KEY_MINUTES] = (int)$convertTime->format('i');
        }

        if ($isChanged) {
            $schedule[DataSourceIntegration::SCHEDULE_KEY_CHECK_AT] = $checkAts;
            $dataSourceIntegration->setSchedule($schedule);
            $this->dataSourceIntegrationManager->save($dataSourceIntegration);
            $this->logger->info('Update checkAt timezone for dataSource integration id ' . $dataSourceIntegration->getId());
            return true;
        }

        return false;
    }

    /**
     * @param DataSourceInterface $dataSource
     * @return bool
     */
    private function migrateTimeZoneAlertSettingForDataSource($dataSource)
    {
        if (!$dataSource instanceof DataSourceInterface) {
            return false;
        }

        $alertSettings = $dataSource->getAlertSetting();

        if (!is_array($alertSettings)) {
            return false;
        }

        $isChanged = false;
        foreach ($alertSettings as &$alertSetting) {
            if (!is_array($alertSetting)) {
                continue;
            }

            if (!array_key_exists(DataSourceAlertInterface::ALERT_TYPE_KEY, $alertSetting)) {
                continue;
            }

            $type = $alertSetting[DataSourceAlertInterface::ALERT_TYPE_KEY];

            if ($type != DataSourceAlertInterface::ALERT_DATA_NO_RECEIVED_KEY) {
                continue;
            }

            if (!array_key_exists(DataSourceAlertInterface::ALERT_TIME_ZONE_KEY, $alertSetting)) {
                continue;
            }

            $currentTimeZone = $alertSetting[DataSourceAlertInterface::ALERT_TIME_ZONE_KEY];

            if (empty($currentTimeZone)) {
                continue;
            }

            if (in_array($currentTimeZone, self::KEEP_TIMEZONES)) {
                continue;
            }

            if (array_key_exists($currentTimeZone, self::CONVERT_TIMEZONES)) {
                $convertTimeZone = new \DateTimeZone(self::CONVERT_TIMEZONES[$currentTimeZone]);
            } else {
                $convertTimeZone = new \DateTimeZone('UTC');
            }

            if (array_key_exists(DataSourceAlertInterface::ALERT_HOUR_KEY, $alertSetting)) {
                $alertHour = $alertSetting[DataSourceAlertInterface::ALERT_HOUR_KEY];
            } else {
                $alertHour = 1;
            }

            if (array_key_exists(DataSourceAlertInterface::ALERT_MINUTE_KEY, $alertSetting)) {
                $alertMinutes = $alertSetting[DataSourceAlertInterface::ALERT_MINUTE_KEY];
            } else {
                $alertMinutes = 0;
            }

            $isChanged = true;
            $convertTime = new \DateTime('now', new \DateTimeZone($currentTimeZone));
            $convertTime->setTime($alertHour, $alertMinutes);
            $convertTime->setTimezone($convertTimeZone);

            $alertSetting[DataSourceAlertInterface::ALERT_TIME_ZONE_KEY] = $convertTimeZone->getName();
            $alertSetting[DataSourceAlertInterface::ALERT_HOUR_KEY] = (int)$convertTime->format('H');
            $alertSetting[DataSourceAlertInterface::ALERT_MINUTE_KEY] = (int)$convertTime->format('i');
        }

        if ($isChanged) {
            $dataSource->setAlertSetting($alertSettings);
            $this->dataSourceManager->save($dataSource);
            $this->logger->info('Update checkAt timezone for dataSource ' . $dataSource->getName() . ', id ' . $dataSource->getId());
            return true;
        }

        return false;
    }
}