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

        return $this->createDownloadableCsvFile($dataset);
    }

    /**
     * Reads in the original csv and creates a downloadable one, by adding all the mapped records form the database
     *
     * @param $dataset
     * @return bool
     */
    protected function createDownloadableCsvFile($dataset)
    {
        $app = $this->getSilexApplication();

        $rows = $app['csv_service']->getRows($dataset);
        $headerRow = $app['csv_service']->getColumns($dataset);
        if ($headerRow) {
            $data = array('hg_id','hg_uri', 'hg_name', 'hg_geometry', 'hg_type', 'hg_dataset');
            foreach ($data as $ding) {
                array_push($headerRow, $ding);
            }
        }

        // fetch matching records, one by one
        foreach ($rows as $rowId => &$row) {
            $originalName = $row[(int) $dataset['placename_column']];

            /** @var DatasetService $dataService */
            $dataService = $app['dataset_service'];
            // fetching by rowId to prevent accidental fetching of Same Place, liesIn somewhere else
            $record = $dataService->fetchRecordByRowId($rowId, $dataset['id']);

            // add db data to th csv file to Write
            array_push($row, $record['hg_id']);
            array_push($row, $record['hg_uri']);
            array_push($row, $record['hg_name']);
            array_push($row, $record['hg_geometry']);
            array_push($row, $record['hg_type']);
            array_push($row, $record['hg_dataset']);
        }

        // create a new file
        $newfile = $app['upload_dir'] . DIRECTORY_SEPARATOR . 'download_' . $dataset['filename'];

        $writer = Writer::createFromFileObject(new SplTempFileObject());
        if (0 < mb_strlen($dataset['delimiter'])) {
            $writer->setDelimiter($dataset['delimiter']);
        } else {
            $writer->setDelimiter(",");
        }
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
        $app = $this->getSilexApplication();

        $datasetId = $input->getArgument('dataset');
        $app['monolog']->addInfo('Standardize process called for dataset ' . $datasetId);

        /** @var DatasetService $dataService */
        $dataService = $app['dataset_service'];

        $dataset = $dataService->fetchDataset($datasetId);
        $liesIn = false;
        if (strlen($dataset['liesin_column']) > 0) {
            $liesIn = true;
        }

        $rows = $dataService->fetchRecordsToStandardize($datasetId, $liesIn);

        $app['monolog']->addInfo('Found ' . count($rows) . ' locations to process.');

        if (strlen($dataset['placename_column']) < 1) {
            $dataService->setMappingFailed($datasetId);
            return $app['monolog']->addError('No field mapping was provided, so could not standardize.');
        }

        /** @var GeocoderService $geocoder */
        $geocoder = $app['geocoder_service'];

        try {
            $dataService->setMappingStarted($datasetId);

            if (false === $geocoder->map($rows, $dataset)) {
                $dataService->setMappingFailed($dataset['id']);

                return $app['monolog']->addError('No response could be retrieved from the Histograph API.');
            }

            // get user via dataset user_id
            $user = $dataService->getUser($dataset['user_id']);
            $app['monolog']->addInfo('Sending an email to user, with id: ' . $dataset['user_id']);

            $dataService->setMappingFinished($dataset['id']);

            $message = \Swift_Message::newInstance()
                ->setSubject($app['sitename'] . ' CSV-bestand verwerkt')
                ->setFrom(array('histograph.io@gmail.com'))
                ->setTo(array($user['email']))
                ->setBody("Beste {$user['name']},

Uw plaatsnamenbestand '{$dataset['name']}' is verwerkt. Kijk op onderstaande link om de resultaten in te zien of te downloaden.

http://standaardiseren.erfgeo.nl/datasets/{$dataset['id']}

                ");

            $app['mailer']->send($message);

            $this->createDownloadableCsvFile($dataset);

        } catch (\Exception $e) {
            $dataService->setMappingFailed($dataset['id']);

            return $app['monolog']->addError('CLI error: Histograph API returned error: ' . $e->getMessage());
        }
    }
}
