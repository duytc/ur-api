<?php

namespace UR\Bundle\AppBundle\Command;


use Symfony\Bridge\Monolog\Logger;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\IntegrationManagerInterface;
use UR\Model\Core\IntegrationInterface;
use UR\Service\StringUtilTrait;

class ListIntegrationCommand extends ContainerAwareCommand
{
    use StringUtilTrait;

    /** @var Logger */
    private $logger;

    protected function configure()
    {
        $this
            ->setName('ur:integration:list')
            ->addArgument('search', InputArgument::OPTIONAL, 'search by name')
            ->setDescription('List Integration');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        /** @var Logger $logger */
        $this->logger = $container->get('logger');

        /* get inputs */
        $search = $input->getArgument('search');

        /** @var IntegrationManagerInterface $integrationManager */
        $integrationManager = $container->get('ur.domain_manager.integration');
        $integrations = ($search == null)
            ? $integrationManager->all()
            : $integrationManager->findByName($search);

        $output->writeln(sprintf('%d integrations found.', count($integrations)));
        $this->prettyPrint($output, $integrations);
    }

    /**
     * @param OutputInterface $output
     * @param array|IntegrationInterface[] $integrations
     */
    private function prettyPrint(OutputInterface $output, array $integrations)
    {
        $output->writeln(sprintf('-- # -- name --------- c-name ---------- params ----------'));
        $output->writeln(sprintf('|       |              |                 |                |'));

        $i = 0;
        foreach ($integrations as $integration) {
            $i++;

            $params = $integration->getParams();
            $params = is_array($params) ? $this->getParamsString($params) : 'NULL';

            $output->writeln(sprintf('-- %d -- %s --------- %s ---------- %s ----------',
                $i,
                $integration->getName(),
                $integration->getCanonicalName(),
                $params
            ));

            $output->writeln(sprintf('|      |              |                 |                 |'));
        }
    }

    /**
     * get Params String from params
     *
     * @param array $params array as [[ key => <param name>, type => <param type> ], ...]
     * @return string
     */
    private function getParamsString(array $params)
    {
        $paramsStringArr = [];

        foreach ($params as $param) {
            if (!is_array($param) || (!array_key_exists('key', $param) && !array_key_exists('type', $param))) {
                continue;
            }

            $paramsStringArr[] = sprintf('%s:%s', $param['key'], $param['type']);
        }

        return implode(',', $paramsStringArr);
    }
}