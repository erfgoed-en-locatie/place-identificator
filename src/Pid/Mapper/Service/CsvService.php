<?php

namespace Pid\Mapper\Service;


use League\Csv\Reader;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;


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

        $csv = Reader::createFromPath($file);
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

    public function writeTestFile($dataset, $rows)
    {
        $testFile = $this->uploadDir . DIRECTORY_SEPARATOR . $this->testPrefix . $dataset['filename'];

        $writer = Writer::createFromFileObject(new SplTempFileObject());
        $writer->setDelimiter($dataset['delimiter']);
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
        $csv = Reader::createFromPath($file);
        if (0 < mb_strlen($dataset['delimiter'])) {
            $csv->setDelimiter($dataset['delimiter']);
        } else {
            $csv->setDelimiter(current($csv->detectDelimiterList(2)));
        }

        return $csv->toHTML('table table-striped table-bordered table-hover');
    }
}