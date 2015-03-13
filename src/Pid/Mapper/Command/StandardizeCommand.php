<?php

namespace Pid\Mapper\Command;

use Knp\Command\Command;
use Pid\Mapper\Service\GeocoderService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class StandardizeCommand
 *
 * Console command to process an entire dataset and mail the user when all is done
 */
class StandardizeCommand extends Command {

    protected function configure()
    {
        $this
            ->setName('standardize')
            ->setDescription('Call the Histograpgh API to standardize place names')
            ->addArgument('dataset', InputArgument::REQUIRED, 'Id of the dataset to proces')
            ->addOption('test', null, InputOption::VALUE_NONE, 'If set, a test run is done withut persisting the changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $datasetId = $input->getArgument('dataset');
        $app = $this->getSilexApplication();

        $app['monolog']->addInfo('CLI called');

        $dataset = $app['dataset_service']->fetchDataset($datasetId);

        $file = $app['upload_dir'] . DIRECTORY_SEPARATOR . $dataset['filename'];
        if (!file_exists($file)) {
            return $app['monolog']->addError('CLI error: het csv-bestand (' . $dataset['filename'] . ') kon niet gelezen worden.');
        }
        $csv = \League\Csv\Reader::createFromPath($file);

        $rows =
            $csv->setOffset(0)
                // skipping empty rows
                ->addFilter(function($row) {
                    if (!empty($row[0])) {
                        return $row;
                    }
                })
                ->fetchAll();
        if ($dataset['skip_first_row']) {
            array_shift($rows);
        }
        $placeColumn = (int) $app['dataset_service']->getPlaceColumnForDataset($datasetId);

        /** @var GeocoderService $geocoder */
        $geocoder = $app['geocoder_service'];
        try {
            $mappedRows = $geocoder->map($rows, $placeColumn);

            if ($input->getOption('test')) {
                // do not store anything
                $app['dataset_service']->storeMappedRecords($mappedRows, $placeColumn, $id);
            }

            // todo send an email
            // get user via dataset user_id
            $app['monolog']->addInfo('User: ' . $dataset['user_id']);


        } catch (\Exception $e) {
            return $app['monolog']->addError('CLI error: Histograph API returned error: ' . $e->getMessage());
        }

        $output->writeln('All done');
    }
}
