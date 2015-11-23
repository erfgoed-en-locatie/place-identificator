<?php

namespace Pid\Mapper\Service;


use League\Csv\Reader;
use League\Csv\Writer;
use SplTempFileObject;


/**
 * Reads and writes to and from the specific csv
 *
 * @package Pid\Mapper\Service
 */
class CsvService
{

    protected $uploadDir;
    protected $testPrefix = 'test_';

    public function __construct($uploadDir)
    {
        $this->uploadDir = $uploadDir;
    }

    public function isFileReadable($dataset)
    {
        $file = $this->uploadDir . DIRECTORY_SEPARATOR . $dataset['filename'];
        if (!file_exists($file)) {
            return false;
        }

        return true;
    }

    /**
     *
     * @param $dataset
     * @return static
     */
    private function read($dataset)
    {
        $file = $this->uploadDir . DIRECTORY_SEPARATOR . $dataset['filename'];

        $fileContent = file_get_contents($file);
        // fix for weird CR characters that some people seem to use:
        if (strpos($fileContent, "\r")) {
            $converted = preg_replace('~\r\n?~', "\n", $fileContent);
            $csv = Reader::createFromString($converted);
        } else {
            $csv = Reader::createFromString($fileContent);
        }

        if (0 < mb_strlen($dataset['delimiter'])) {
            $csv->setDelimiter($dataset['delimiter']);
        } else {
            $csv->setDelimiter(current($csv->detectDelimiterList(2)));
        }
        if (0 < mb_strlen($dataset['enclosure_character'])) {
            $csv->setEnclosure($dataset['enclosure_character']);
        }
        if (0 < mb_strlen($dataset['escape_character'])) {
            $csv->setEscape($dataset['escape_character']);
        }

        return $csv;
    }

    /**
     * Get the header row
     *
     * @param $dataset
     * @return mixed
     */
    public function getColumns($dataset)
    {
        $csv = $this->read($dataset);

        return $csv->fetchOne();
    }

    /**
     * @param $dataset
     * @param null $limit
     * @return mixed
     */
    public function getRows($dataset, $limit = null)
    {
        $csv = $this->read($dataset);

        if (null === $limit) {
            $csvRows = $csv->setOffset(0)->fetchAll();
        } else {
            $csvRows = $csv->setOffset(0)->setLimit($limit)->fetchAll();
        }
        if ($dataset['skip_first_row']) {
            array_shift($csvRows);
        }

        return $csvRows;
    }

    /**
     * Write a test file
     *
     * @param $dataset
     * @param $rows
     */
    public function writeTestFile($dataset, $rows)
    {
        $testFile = $this->uploadDir . DIRECTORY_SEPARATOR . $this->testPrefix . $dataset['filename'];
        $writer = Writer::createFromFileObject(new SplTempFileObject());

        if (0 < mb_strlen($dataset['delimiter'])) {
            $writer->setDelimiter($dataset['delimiter']);
        } else {
            $writer->setDelimiter(",");
        }

        $headerRow = array(
            'naam',
            'ligt in',
            'dataset id',
            'hg dataset',
            'status',
            'hits',
            'hg identifier',
            'hg naam',
            'hg type',
            'hg uri',
            'geometrie'
        );
        $writer->setEncodingFrom("utf-8");
        if ($headerRow) {
            $writer->insertOne($headerRow);
        }

        $writer->insertAll($rows);

        file_put_contents($testFile, $writer);
    }

    /**
     * Converts the csv file to html table
     *
     * @param integer $dataset
     * @param string $version original|test
     * @return string
     */
    public function convertCsv2Html($dataset, $version = 'original')
    {
        if ($version == 'test') {
            $file = $this->uploadDir . DIRECTORY_SEPARATOR . $this->testPrefix . $dataset['filename'];
        } else {
            $file = $this->uploadDir . DIRECTORY_SEPARATOR . $dataset['filename'];
        }

        $fileContent = file_get_contents($file);
        // fix for weird CR characters that some people seem to use:
        if (strpos($fileContent, "\r")) {
            $converted = preg_replace('~\r\n?~', "\n", $fileContent);
            $csv = Reader::createFromString($converted);
        } else {
            $csv = Reader::createFromString($fileContent);
        }

        if (0 < mb_strlen($dataset['delimiter'])) {
            $csv->setDelimiter($dataset['delimiter']);
        } else {
            $csv->setDelimiter(current($csv->detectDelimiterList(2)));
        }

        return $csv->toHTML('table table-striped table-bordered table-hover');
    }
}