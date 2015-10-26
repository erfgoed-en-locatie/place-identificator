<?php

namespace Pid\Mapper\Command;

use Knp\Command\Command;
use Pid\Mapper\Service\DatasetService;
use Pid\Mapper\Service\GeocoderService;
use SplTempFileObject;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use League\Csv\Writer;

/**
 * Class StandardizeCommand
 *
 * Console command to process an entire dataset and mail the user when all is done
 */
class StandardizeCommand extends Command
{

    protected function configure()
    {
        $this
            ->setName('standardize')
            ->setDescription('Call the Histograpgh API to standardize place names')
            ->addArgument('dataset', InputArgument::REQUIRED, 'Please provide a dataset Id')
            ->addOption('method', null, InputOption::VALUE_REQUIRED,
                'Please set a method to use: api or download'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ('download' === $input->getOption('method')) {
            return $this->download($input);
        } else {
            return $this->standardize($input);
        }
    }

    protected function download(InputInterface $input)
    {
        $app = $this->getSilexApplication();
        $datasetId = $input->getArgument('dataset');
        $app['monolog']->addInfo('Creating downloadable file for dataset ' . $datasetId);

        /** @var DatasetService $dataService */
        $dataService = $app['dataset_service'];

        $dataset = $dataService->fetchDataset($datasetId);
        $fieldMapping = $dataService->getFieldMappingForDataset($datasetId);

        return $this->createDownloadableCsvFile($dataset, $fieldMapping);
    }

    /**
     * Reads in the original csv and creates a downloadable one, by adding all the mapped records form the database
     *
     * @param $dataset
     * @return bool
     */
    protected function createDownloadableCsvFile($dataset, $fieldMapping)
    {
        $app = $this->getSilexApplication();

        $file = $app['upload_dir'] . DIRECTORY_SEPARATOR . $dataset['filename'];
        $csv = \League\Csv\Reader::createFromPath($file);
        $csv->setDelimiter(current($csv->detectDelimiterList(2)));
        $rows =
            $csv->setOffset(0)
                // skipping empty rows
                ->addFilter(function ($row) {
                    if (!empty($row[0])) {
                        return $row;
                    }
                })
                ->fetchAll();
        if ($dataset['skip_first_row']) {
            $headerRow = $rows[0];
            array_shift($rows);
        }

        if ($headerRow) {
            $data = array('hg_id','hg_uri', 'hg_name', 'hg_geometry', 'hg_type', 'hg_dataset');
            foreach ($data as $ding) {
                array_push($headerRow, $ding);
            }
        }

        // fetch matching records, one by one?
        $placeColumn = (int)$fieldMapping['placename'];
        foreach ($rows as &$row) {
            $originalName = $row[(int)($fieldMapping['placename'])];

            /** @var DatasetService $dataService */
            $dataService = $app['dataset_service'];
            $record = $dataService->fetchRecordByName($originalName);

            // add db data to th csv file to Write
            array_push($row, $record['hg_id']);
            array_push($row, $record['hg_uri']);
            array_push($row, $record['hg_name']);
            array_push($row, $record['hg_geometry']);
            array_push($row, $record['hg_type']);
            array_push($row, $record['hg_dataset']);
        }

        // create a new file
        $newfile = $app['upload_dir'] . DIRECTORY_SEPARATOR . 'download_' . $dataset['id'];

        $writer = Writer::createFromFileObject(new SplTempFileObject());
        $writer->setDelimiter(",");
        //$writer->setNewline("\r\n");
        $writer->setEncodingFrom("utf-8");
        if ($headerRow) {
            $writer->insertOne($headerRow);
        }
        $writer->insertAll($rows);

        file_put_contents($newfile, $writer);

        return true;
    }

    /**
     *
     * @param InputInterface $input
     * @return mixed
     */
    protected function standardize(InputInterface $input)
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
        $csv->setDelimiter(current($csv->detectDelimiterList(2)));

        $rows =
            $csv->setOffset(0)
                // skipping empty rows
                ->addFilter(function ($row) {
                    if (!empty($row[0])) {
                        return $row;
                    }
                })
                ->fetchAll();

        $app['monolog']->addInfo('Found ' . count($rows) . ' to process.');
        if ($dataset['skip_first_row']) {
            array_shift($rows);
        }

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

            $this->createDownloadableCsvFile($dataset, $fieldMapping);

        } catch (\Exception $e) {
            $dataService->setMappingFailed($datasetId);

            return $app['monolog']->addError('CLI error: Histograph API returned error: ' . $e->getMessage());
        }
    }
}
