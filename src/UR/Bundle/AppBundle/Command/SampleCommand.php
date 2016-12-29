<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SampleCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ur:sample-command:hello')
            ->addOption('message', 'd', InputOption::VALUE_OPTIONAL, 'The message')
            ->setDescription('Sample command');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $message = $input->getOption('message');
        $container = $this->getContainer();
        $logger = $container->get('logger');
        $logger->info(sprintf('Your message %s. Thanks for using me.', $message));
    }
} 