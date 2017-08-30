<?php

namespace UR\Bundle\AppBundle\Command;

use Exception;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UR\Model\Core\DataSourceInterface;
use UR\Service\DataSource\Csv;
use UR\Service\DataSource\DataSourceType;
use UR\Service\DataSource\Excel;
use UR\Service\DataSource\Excel2007;
use UR\Service\DataSource\Json;
use UR\Service\Import\ImportService;

class RefreshDetectedFieldsForDataSourceCommand extends ContainerAwareCommand
{
    /**
     * @inheritdoc
     */
    protected function configure()
    {
        $this
            ->setName('tc:ur:data-source:refresh-detected-field')
            ->addArgument('path', InputArgument::REQUIRED, 'Standard file which contains the correct headers')
            ->addOption('id', 'id', InputOption::VALUE_REQUIRED, 'Id of the data source being updated')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Id of the data source being updated')
            ->setDescription('Refresh detected fields for Data Source from a standard file');
    }

    /**
     * @inheritdoc
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $file = $input->getArgument('path');
        if (!file_exists($file)) {
            $output->writeln(sprintf('<error>Can not find resource "%s" or you do not have permission to access</error>', $file));
            exit(1);
        }

        $id = $input->getOption('id');
        if (!$id) {
            $output->writeln('<error>"id" can not be empty</error>');
            exit(1);
        }

        $dataSourceManager = $this->getContainer()->get('ur.domain_manager.data_source');
        $dataSource = $dataSourceManager->find($id);
        if (!$dataSource instanceof DataSourceInterface) {
            $output->writeln(sprintf('<error>Can not find resource "%d" or you do not have permission to access</error>', $id));
            exit(1);
        }

        // make sure file extension is supported by data source
        $fileExtension = (new \SplFileInfo($file))->getExtension();
        $dataSourceTypeExtension = DataSourceType::getOriginalDataSourceType($fileExtension);
        if ($dataSource->getFormat() != $dataSourceTypeExtension) {
            $output->writeln(sprintf('<error>Data source only support format "%s", got file extension "%s"</error>', $dataSource->getFormat(), $fileExtension));
            exit(1);
        }

        $force = filter_var($input->getOption('force'), FILTER_VALIDATE_BOOLEAN);
        /** @var ImportService */
        $importService = $this->getContainer()->get('ur.service.import');

        $dataSourceFile = null;
        switch ($dataSource->getFormat()) {
            case DataSourceType::DS_CSV_FORMAT:
                $dataSourceFile = new Csv($file);
                break;
            case DataSourceType::DS_EXCEL_FORMAT:
                $inputFileType = \PHPExcel_IOFactory::identify($file);
                if (in_array($inputFileType, Excel::$EXCEL_2003_FORMATS)) {
                    $dataSourceFile = new Excel($file, $this->getContainer()->getParameter('ur_reading_xls_chunk_size'));
                } else if (in_array($inputFileType, Excel2007::$EXCEL_2007_FORMATS)) {
                    $dataSourceFile = new Excel2007($file, $this->getContainer()->getParameter('ur_reading_xls_chunk_size'));
                }

                break;
            case DataSourceType::DS_JSON_FORMAT:
                try {
                    $dataSourceFile = new Json($file);
                } catch (\Exception $ex) {
                    throw $ex;
                }
                break;
        }

        if (!$dataSourceFile instanceof \UR\Service\DataSource\DataSourceInterface) {
            $output->writeln(sprintf('<error>Can not detect fields with file "%s"</error>', $file));
            exit(1);
        }

        $detectedFields = $importService->getNewFieldsFromFiles($dataSourceFile);
        $fields = json_encode($detectedFields);
        $output->writeln('<info>Detected fields :</info>');
        $output->writeln(sprintf('<info>%s</info>', $fields));
        if ($force) {
            $detectedFields = array_flip($detectedFields);
            foreach ($detectedFields as $key => &$value) {
                $value = 1;
            }
            $dataSource->setDetectedFields($detectedFields);
            $dataSourceManager->save($dataSource);
            $output->writeln('<info>1 data source get updated !</info>');
        }
    }
}