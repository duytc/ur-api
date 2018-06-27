<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\OptimizationRuleManagerInterface;
use UR\Model\Core\OptimizationRuleInterface;

class RefreshTagcadeApiCacheCommand extends ContainerAwareCommand
{
    const COMMAND_NAME = 'ur:auto-optimization:test:reactivate-scoring-integration';
    const INPUT_DATA_FORCE = 'force';
    const ALL = 'all';
    const RULES = 'optimizationRules';

    /** @var OptimizationRuleManagerInterface */
    private $optimizationRuleManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addOption(self::ALL, 'a', InputOption::VALUE_NONE,
                'Enable for all optimization rules (optional)')
            ->addOption(self::RULES, 'u', InputOption::VALUE_OPTIONAL,
                'Enable for special optimization rules (optional), allow multiple optimization id separated by comma, e.g. -u "5,10,3"')
            ->setDescription('The test command to synchronize Optimization Rule and data training table,'
                . ' call scoring service to get new scores,'
                . ' then notify new scores to platform integrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $manager = $container->get('ur.worker.manager');
        $this->optimizationRuleManager = $container->get('ur.domain_manager.optimization_rule');
            
        $io = new SymfonyStyle($input, $output);

        $io->section('Starting command reactive scoring integration ...');

        $optimizationRules = $this->getOptimizationRulesFromInput($input, $output);
        if (count($optimizationRules) < 1) {
            return;
        }

        foreach ($optimizationRules as $optimizationRule) {
            if (!$optimizationRule instanceof OptimizationRuleInterface) {
                continue;
            }

            $manager->syncTrainingDataAndGenerateLearnerModel($optimizationRule->getId());
        }

        $io->section('Complete add jobs for refresh optimization cache!');

    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return mixed
     */
    private function getOptimizationRulesFromInput(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $allOptimizationRule = $input->getOption(self::ALL);
        $optimizationRuleIds = $input->getOption(self::RULES);

        if (empty($allOptimizationRule) &&
            empty($optimizationRuleIds)
        ) {
            $io->warning('No optimization rules found. Please recheck your input');

            return [];
        }

        if (!empty($allOptimizationRule)) {
            return $this->optimizationRuleManager->all();
        }

        if (!empty($optimizationRuleIds)) {
            $optimizationRuleIds = explode(",", $optimizationRuleIds);
            $optimizationRules = [];

            foreach ($optimizationRuleIds as $optimizationRuleId) {
                $optimizationRule = $this->optimizationRuleManager->find($optimizationRuleId);

                if (!$optimizationRule instanceof OptimizationRuleInterface) {
                    $io->warning(sprintf('No optimization rule found by id: %s', $optimizationRuleId));
                    continue;
                }

                $optimizationRules[] = $optimizationRule;
            }

            if (count($optimizationRules) < 1) {
                $io->warning('No optimization rules found. Please recheck your input');
            }

            return $optimizationRules;
        }

        $io->warning('No optimization rules found. Please recheck your input');

        return [];
    }
}