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
            ->addArgument('dataset', InputArgument::REQUIRED, 'Id of the dataset to process')
            ->addOption('test', null, InputOption::VALUE_NONE, 'If set, a test run is done without persisting the changes');
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
        $fieldMapping = $app['dataset_service']->getFieldMappingForDataset($datasetId);

        $placeColumn = (int) $fieldMapping['placename'];
        $searchOn = (int) $fieldMapping['search_option'];

        /** @var GeocoderService $geocoder */
        $geocoder = $app['geocoder_service'];
        $geocoder->setSearchOn($searchOn);
        try {
            $app['dataset_service']->setMappingStarted($datasetId);

            $mappedRows = $geocoder->map($rows, $placeColumn);

            $app['dataset_service']->storeMappedRecords($mappedRows, $placeColumn, $datasetId);

            // get user via dataset user_id
            $user = $app['dataset_service']->getUser($dataset['user_id']);
            $app['monolog']->addError('Sending an email to user, with id: ' . $dataset['user_id']);

            $app['dataset_service']->setMappingFinished($datasetId);

            $message = \Swift_Message::newInstance()
                ->setSubject($app['sitename'] . ' CSV-bestand verwerkt')
                ->setFrom(array('histograph.io@gmail.com'))
                ->setTo(array($user['email']))
                ->setBody("Beste {$user['name']},

Uw plaatsnamenbestand '{$dataset['name']}' is verwerkt. Kijk op onderstaande link om de resultaten in te zien of te downloaden.

http://locatienaaruri.erfgeo.nl/datasets/{$datasetId}

                ");

            $app['mailer']->send($message);

        } catch (\Exception $e) {
            $app['dataset_service']->setMappingFailed($datasetId);
            return $app['monolog']->addError('CLI error: Histograph API returned error: ' . $e->getMessage());
        }

        $output->writeln('All done');
    }
}
