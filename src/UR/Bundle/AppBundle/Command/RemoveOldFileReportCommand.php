<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RemoveOldFileReportCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:report:remove-old-file-report';

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('Command to remove old report files. Need run every day');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $removeOutOfDateReportService = $container->get('ur.services.large_report.remove_out_of_date_report_service');
        $count = $removeOutOfDateReportService->removeOutOfDateReport();
        $output->writeln("Command run success, Removed $count files.");
    }
}