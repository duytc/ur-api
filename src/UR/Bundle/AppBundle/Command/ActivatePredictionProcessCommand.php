<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\OptimizationRuleManagerInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\RestClientTrait;

class ActivatePredictionProcessCommand extends ContainerAwareCommand
{
    use RestClientTrait;

    const COMMAND_NAME = 'ur:auto-optimization:test:notify-scoring-integration';
    const STATUS_KEY = 'status';
    const MESSAGE_KEY = 'message';
    const DATA_KEY = 'data';
    const IDENTIFIERS_KEY = 'identifiers';

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument('optimizationRuleId', InputArgument::REQUIRED, 'Auto Optimization Rule Id')
            ->setDescription('The test command to notify new scores to platform integrations for the Optimization Rule with given id');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $io = new SymfonyStyle($input, $output);

        /* get inputs */
        $optimizationRuleId = $input->getArgument('optimizationRuleId');

        if (empty($optimizationRuleId)) {
            $io->warning('Missing optimizationRuleId');
            return;
        }

        $io->section(sprintf('Starting command refresh cache for optimization rule =%d', $optimizationRuleId));

        /* find OptimizationIntegration */
        /** @var OptimizationRuleManagerInterface $optimizationRuleManager */
        $optimizationRuleManager = $container->get('ur.domain_manager.optimization_rule');

        /** @var OptimizationRuleInterface $optimizationRule */
        $optimizationRule = $optimizationRuleManager->find($optimizationRuleId);
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            $io->warning(sprintf('OptimizationRule #%d not found', $optimizationRuleId));
            return;
        }

        $adSlotOptimizeService = $this->getContainer()->get('ur.service.optimization_rule.automated_optimization.automated_optimizer');

        try {
            $adSlotOptimizeService->optimizeForRule($optimizationRule);
        } catch (\Exception $e) {
            $io->warning(sprintf('There is an exception: %s for optimization rule %d', $e->getMessage(), $optimizationRule->getId()));
        }

        $io->section(sprintf('notify scoring integration for optimization rule %d', $optimizationRule->getId()));;
    }
}