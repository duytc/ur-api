<?php

namespace Tagcade\Bundle\AppBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Tagcade\Model\Core\AdNetworkInterface;
use Tagcade\Model\Core\AdTagInterface;

class ActivateNetworkCommand extends ContainerAwareCommand
{

    protected function configure()
    {
        $this
            ->setName('tc:ad-network:activate')
            ->addOption('ad-network', 'd', InputOption::VALUE_OPTIONAL, 'ad network id to be activated.')
            ->setDescription('Do activate for ad networks that get paused by exceeding its impression or opportunity cap setting value');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $adNetwork = $input->getOption('ad-network');
        $container = $this->getContainer();
        $logger = $container->get('logger');
        $adNetworkManager = $container->get('tagcade.domain_manager.ad_network');
        $adTagManager = $container->get('tagcade.domain_manager.ad_tag');
        $em = $container->get('doctrine.orm.entity_manager');
        $activateForAdNetworks = [];

        if ($adNetwork != null) {
            $adNetwork = $adNetworkManager->find($adNetwork);
            if (!$adNetwork instanceof AdNetworkInterface) {
                throw new \Exception(sprintf('Not found that ad network %s', $adNetwork));
            }

            $activateForAdNetworks[] = $adNetwork;
        }

        if (count($activateForAdNetworks) < 1) {
            $activateForAdNetworks = $adNetworkManager->allHasCap();
        }

        $pausedNetworkCount = 0;
        foreach ($activateForAdNetworks as $nw) {
            /**
             * @var AdNetworkInterface $nw
             */
            $adTags = $adTagManager->getAdTagsForAdNetwork($nw);
            if (count($adTags) < 1) {
                continue;
            }

            $logger->info(sprintf('Ad network %d is being activated', $nw->getId()));

            foreach ($adTags as $adTag) {
                /**
                 * @var AdTagInterface $adTag
                 */
               if ($adTag->isAutoPaused()) {
                   $adTag->activate();
               }
            }
        }

        $em->flush();

        $logger->info(sprintf('There are %d ad networks get activated', $pausedNetworkCount));
    }
} 