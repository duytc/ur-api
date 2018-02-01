<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use UR\DomainManager\AutoOptimizationConfigManagerInterface;
use UR\Model\Core\AutoOptimizationConfigInterface;
use UR\Service\RestClientTrait;

class ActivateLearnerCommand extends ContainerAwareCommand
{
    use RestClientTrait;

    const COMMAND_NAME = 'ur:auto-optimization:activate-learner';
    const STATUS_KEY = 'status';
    const MESSAGE_KEY = 'message';
    const DATA_KEY = 'data';
    const IDENTIFIERS_KEY = 'identifiers';

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument('autoOptimizationConfigId', InputArgument::REQUIRED, 'Auto Optimization Config Id')
            ->setDescription('activate learner for AutoOptimizationConfig with given id');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $io = new SymfonyStyle($input, $output);

        $io->section('Starting command...');

        /* get inputs */
        $autoOptimizationConfigId = $input->getArgument('autoOptimizationConfigId');

        if (empty($autoOptimizationConfigId)) {
            $io->warning('Missing autoOptimizationConfigId');
            return;
        }

        /* find AutoOptimizationConfig */
        /** @var AutoOptimizationConfigManagerInterface $autoOptimizationConfigManager */
        $autoOptimizationConfigManager = $container->get('ur.domain_manager.auto_optimization_config');

        /** @var AutoOptimizationConfigInterface $autoOptimizationConfig */
        $autoOptimizationConfig = $autoOptimizationConfigManager->find($autoOptimizationConfigId);
        if (!$autoOptimizationConfig instanceof AutoOptimizationConfigInterface) {
            $io->warning(sprintf('AutoOptimizationConfig #%d not found', $autoOptimizationConfigId));
            return;
        }

        $activateLearnerLink = $container->getParameter('activate_learner_link');
        $activateLeanerMethod = 'POST';
        $data = ['autoOptimizationConfigId' => $autoOptimizationConfig->getId(),
            'token' => $autoOptimizationConfig->getToken()];

        $io->text(sprintf("Active learner for auto optimization config name: %s", $autoOptimizationConfig->getName()));

        $result = $this->callRestAPI($activateLeanerMethod, $activateLearnerLink, json_encode($data));

        $result = json_decode($result, true);
        if (empty($result)) {
            $io->text('There is a error in learner');
            return;
        }

        $status = $this->getStatus($result);
        $message = $this->getMessage($result);
        $identifiers = $this->getIdentifiers($result);

        if (Response::HTTP_OK != $status) {
            $io->text($message);
        } else {
            $io->text($message);
            $io->text('The learner model of the identifiers are created:');
            $io->listing($identifiers);
        }
    }

    /**
     * @param $result
     * @return mixed
     */
    private function getStatus($result)
    {
        if (!array_key_exists(self::STATUS_KEY, $result)) {
            return '';
        }
        return $result['' . self::STATUS_KEY . ''];
    }

    /**
     * @param $result
     * @return mixed
     */
    private function getMessage($result)
    {
        if (!array_key_exists(self::MESSAGE_KEY, $result)) {
            return '';
        }

        return $result['' . self::MESSAGE_KEY . ''];
    }

    /**
     * @param $result
     * @return mixed
     */
    private function getIdentifiers($result)
    {
        if (!array_key_exists(self::DATA_KEY, $result)) {
            return '';
        }

        if (!array_key_exists(self::IDENTIFIERS_KEY, $result['' . self::DATA_KEY . ''][0])) {
            return '';
        }

        return $result['' . self::DATA_KEY . ''][0]['' . self::IDENTIFIERS_KEY . ''];
    }
}