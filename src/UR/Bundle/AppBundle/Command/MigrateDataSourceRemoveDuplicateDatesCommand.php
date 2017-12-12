<?php

namespace UR\Bundle\AppBundle\Command;


use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MigrateDataSourceRemoveDuplicateDatesCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:migrate:data-source:remove-duplicate-dates';
    const DATA_SOURCE_TABLE = 'core_data_source';
    const FIELD_TIME_SERIES = 'time_series';
    const FIELD_REMOVE_DUPLICATE_DATES = 'remove_duplicate_dates';
    const FIELD_TYPE = 'TINYINT(1) DEFAULT 0';
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Migrate Data Source from Time Series to Remove Duplicate Dates');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $io = new SymfonyStyle($input, $output);

        /** @var EntityManagerInterface $em */
        $em = $container->get('doctrine.orm.entity_manager');
        $connection = $em->getConnection();

        $sql = sprintf("ALTER TABLE %s CHANGE %s %s %s;", self::DATA_SOURCE_TABLE, self::FIELD_TIME_SERIES, self::FIELD_REMOVE_DUPLICATE_DATES, self::FIELD_TYPE);

        try {
            $connection->executeQuery($sql);
            $io->success("Migrate successfully from Time Series to Remove Duplicate Dates");
        } catch (\Exception $e) {
            
        }
    }
}