<?php

namespace UR\Bundle\AppBundle\Command;


use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UR\DomainManager\DataSourceManagerInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\Parser\Transformer\Column\DateFormat;

class MigrateDataSourceDateRangeDetectionCommand extends ContainerAwareCommand
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var DataSourceManagerInterface
     */
    private $dataSourceManager;

    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('ur:migrate:data-source:date-range-detection:update')
            ->setDescription('Migrate Data Source Date Range Detection: update date_formats with timezone from e, O, P to T');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var ContainerInterface $container */
        $container = $this->getContainer();
        $this->logger = $container->get('logger');
        $this->dataSourceManager = $container->get('ur.domain_manager.data_source');

        $dataSources = $this->dataSourceManager->all();

        $output->writeln(sprintf('There are %s Data Source will be scan to update.', count($dataSources)));

        // migrate alert params
        $migratedCount = $this->migrateUpdateDateRangeDetection($output, $dataSources);

        $output->writeln(sprintf('The command runs successfully: %d data source.', $migratedCount));
    }

    /**
     * Migrate Data Source Date Range Detection
     *
     * @param OutputInterface $output
     * @param array|DataSourceInterface[] $dataSources
     * @return int migrated update date_formats count
     */
    private function migrateUpdateDateRangeDetection(OutputInterface $output, array $dataSources)
    {
        $migratedCount = 0;

        foreach ($dataSources as $dataSource) {
            // sure is DataSourceInterface
            if (!$dataSource instanceof DataSourceInterface) {
                continue;
            }

            /*
             * get date_format and then update
             */
            $dateFormats = $dataSource->getDateFormats();

            if (is_array($dateFormats)) {
                foreach ($dateFormats as &$dateFormat) {
                    $correctText = $dateFormat[DateFormat::FORMAT_KEY];
                    if (empty($correctText)) continue;
                    if (false !== strpos($correctText, 'e') || false !== strpos($correctText, 'O') || false !== strpos($correctText, 'P')) {
                        $correctText = str_replace('e', 'T', $correctText);
                        $correctText = str_replace('O', 'T', $correctText);
                        $correctText = str_replace('P', 'T', $correctText);

                        $dateFormat[DateFormat::FORMAT_KEY] = $correctText;

                        $migratedCount++;
                        $output->writeln(sprintf('Updating for data source - id %s.', $dataSource->getId()));
                    }
                }

                $dataSource->setDateFormats($dateFormats);
                $this->dataSourceManager->save($dataSource);
            }
        }

        return $migratedCount;
    }
}