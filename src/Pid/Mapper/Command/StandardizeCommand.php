<?php

namespace Pid\Mapper\Command;

use Knp\Command\Command;
use Pid\Mapper\Service\DatasetService;
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

        $app['monolog']->addInfo('Standardize process called for dataset ' . $datasetId);

        /** @var DatasetService $dataService */
        $dataService = $app['dataset_service'];

        $dataset = $dataService->fetchDataset($datasetId);

        $file = $app['upload_dir'] . DIRECTORY_SEPARATOR . $dataset['filename'];
        if (!file_exists($file)) {
            return $app['monolog']->addError('CLI error: het csv-bestand (' . $dataset['filename'] . ') kon niet gelezen worden.');
        }
        $csv = \League\Csv\Reader::createFromPath($file);
        // detect delimiter:
        $csv->setDelimiter(current($csv->detectDelimiterList(2)));


        $rows =
            $csv->setOffset(0)
                // skipping empty rows
                ->addFilter(function($row) {
                    if (!empty($row[0])) {
                        return $row;
                    }
                })
                ->fetchAll();

        $app['monolog']->addInfo('Found ' . count($rows) . ' to process.');
        if ($dataset['skip_first_row']) {
            array_shift($rows);
        }

        // todo; create batches!

        $fieldMapping = $dataService->getFieldMappingForDataset($datasetId);
        if (!$fieldMapping) {
            $app['session']->getFlashBag()->set('error',
                'Sorry maar er zijn nog geen instellingen voor dat csv-bestand.');


            $dataService->setMappingFailed($datasetId);
        }

        /** @var GeocoderService $geocoder */
        $geocoder = $app['geocoder_service'];

        try {
            $dataService->setMappingStarted($datasetId);
            $dataService->clearRecordsForDataset($datasetId);

            if (false === $geocoder->map($rows, $fieldMapping, $datasetId)) {
                $dataService->setMappingFailed($datasetId);
                return $app['monolog']->addError('No response could be retrieved from the Histograph API.');
            }

            // get user via dataset user_id
            $user = $dataService->getUser($dataset['user_id']);
            $app['monolog']->addInfo('Sending an email to user, with id: ' . $dataset['user_id']);

            $dataService->setMappingFinished($datasetId);

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
            $dataService->setMappingFailed($datasetId);
            return $app['monolog']->addError('CLI error: Histograph API returned error: ' . $e->getMessage());
        }

    }
}
