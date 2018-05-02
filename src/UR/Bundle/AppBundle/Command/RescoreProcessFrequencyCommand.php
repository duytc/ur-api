<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RescoreProcessFrequencyCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:auto-optimization:rescore-process-frequency';

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->setDescription('frequency of re-calculating score');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $io = new SymfonyStyle($input, $output);
        $container = $this->getContainer();
        $manager = $container->get('ur.worker.manager');
        $manager->processOptimizationFrequency();
        $io->success(sprintf('Create a jobs update optimization integrations. Please run worker!'));
    }
}