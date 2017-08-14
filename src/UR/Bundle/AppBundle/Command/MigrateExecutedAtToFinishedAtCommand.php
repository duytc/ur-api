<?php

namespace UR\Bundle\AppBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\Model\Core\DataSourceIntegrationScheduleInterface;

class MigrateExecutedAtToFinishedAtCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:migrate:executedat-to-finishedat';

    const TABLE_DATA_SOURCE_INTEGRATION_SCHEDULE = 'core_data_source_integration_schedule';
    const TABLE_DATA_SOURCE_INTEGRATION_BACKFILL_HISTORY = 'core_data_source_integration_backfill_history';

    const ALTER_COMMAND = 'ALTER TABLE %s CHANGE `%s` `%s` %s';
    const ADD_COMMAND = 'ALTER TABLE %s ADD `%s` %s';
    const UPDATE_COMMAND = 'UPDATE %s set `%s` = %s WHERE `%s` %s';

    const FIELD_EXECUTED_AT = 'executed_at';
    const FIELD_NEXT_EXECUTED_AT = 'next_executed_at';
    const FIELD_FINISHED_AT = 'finished_at';
    const FIELD_STATUS = 'status';

    const FIELD_TYPE_DATETIME = 'datetime';
    const FIELD_TYPE_INTEGER = 'int';

    const CONDITION_NOT_NULL = 'is not null';

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Migrate executedAt to finishedAt');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');

        $output->writeln('Starting command migrate executedAt to finishedAt');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $conn = $entityManager->getConnection();

        $updateSQLs = [];

        $updateSQLs[] = sprintf(self::ADD_COMMAND, self::TABLE_DATA_SOURCE_INTEGRATION_SCHEDULE, self::FIELD_STATUS, self::FIELD_TYPE_INTEGER);
        $updateSQLs[] = sprintf(self::ADD_COMMAND, self::TABLE_DATA_SOURCE_INTEGRATION_BACKFILL_HISTORY, self::FIELD_STATUS, self::FIELD_TYPE_INTEGER);

        /** Rename executedAt to finishedAt */
        $updateSQLs[] = sprintf(self::ALTER_COMMAND, self::TABLE_DATA_SOURCE_INTEGRATION_SCHEDULE, self::FIELD_EXECUTED_AT, self::FIELD_NEXT_EXECUTED_AT, self::FIELD_TYPE_DATETIME);
        $updateSQLs[] = sprintf(self::ALTER_COMMAND, self::TABLE_DATA_SOURCE_INTEGRATION_BACKFILL_HISTORY, self::FIELD_EXECUTED_AT, self::FIELD_FINISHED_AT, self::FIELD_TYPE_DATETIME);

        /** Set status to finish if finishedAt not null*/
        $updateSQLs[] = sprintf(self::UPDATE_COMMAND, self::TABLE_DATA_SOURCE_INTEGRATION_SCHEDULE, self::FIELD_STATUS, DataSourceIntegrationScheduleInterface::FETCHER_STATUS_NOT_RUN, self::FIELD_FINISHED_AT, self::CONDITION_NOT_NULL);
        $updateSQLs[] = sprintf(self::UPDATE_COMMAND, self::TABLE_DATA_SOURCE_INTEGRATION_BACKFILL_HISTORY, self::FIELD_STATUS, DataSourceIntegrationScheduleInterface::FETCHER_STATUS_FINISHED, self::FIELD_FINISHED_AT, self::CONDITION_NOT_NULL);

        $updateSQLs[] = sprintf('UPDATE `%s` SET `%s` = now() WHERE `%s` = \'0000-00-00 00:00:00\'', self::TABLE_DATA_SOURCE_INTEGRATION_SCHEDULE, self::FIELD_NEXT_EXECUTED_AT, self::FIELD_NEXT_EXECUTED_AT);

        foreach ($updateSQLs as $updateSQL) {
            try {
                $conn->exec($updateSQL);
            } catch (\Exception $e) {

            }
        }

        $output->writeln('Command run successfully');
    }
}