<?php


namespace UR\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use UR\DomainManager\OptimizationRuleManagerInterface;
use UR\Model\Core\OptimizationRuleInterface;
use UR\Service\RestClientTrait;

class ActivateLearningProcessCommand extends ContainerAwareCommand
{
    use RestClientTrait;

    const COMMAND_NAME = 'ur:auto-optimization:test:reactivate-learning-process';
    const STATUS_KEY = 'status';
    const MESSAGE_KEY = 'message';
    const DATA_KEY = 'data';
    const IDENTIFIERS_KEY = 'identifiers';

    protected function configure()
    {
        $this
            ->setName(self::COMMAND_NAME)
            ->addArgument('optimizationRuleId', InputArgument::REQUIRED, 'Auto Optimization Rule Id')
            ->setDescription('The test command to activate learning process for Optimization Rule with given id');
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

        $io->section(sprintf('Starting command activate learning process for optimization rule = %d ...', $optimizationRuleId));

        /* find OptimizationIntegration */
        /** @var OptimizationRuleManagerInterface $optimizationRuleManager */
        $optimizationRuleManager = $container->get('ur.domain_manager.optimization_rule');

        /** @var OptimizationRuleInterface $optimizationRule */
        $optimizationRule = $optimizationRuleManager->find($optimizationRuleId);
        if (!$optimizationRule instanceof OptimizationRuleInterface) {
            $io->warning(sprintf('OptimizationRule #%d not found', $optimizationRuleId));
            return;
        }

        $activateLearnerLink = $container->getParameter('activate_learning_process_url');
        $activateLeanerMethod = 'POST';
        $data = ['optimizationRuleId' => $optimizationRule->getId(),
            'token' => $optimizationRule->getToken()];

        try {
            $result = $this->callRestAPI($activateLeanerMethod, $activateLearnerLink, json_encode($data));

            $result = json_decode($result, true);
            if (empty($result)) {
                $io->text('There is a error in learner: could not parse response');
                return;
            }

            $status = $this->getStatus($result);
            $message = $this->getMessage($result);

            if (Response::HTTP_OK != $status) {
                $io->text($message);
            } else {
                $io->section(sprintf('Activate learning process successfully for optimization rule %d', $optimizationRule->getId()));;
            }
        } catch (\Exception $e) {
            $io->warning("Call Rest API fail. Detail: " . $e);
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