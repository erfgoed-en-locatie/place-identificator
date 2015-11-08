<?php

namespace Pid\Mapper\Service;
use League\Csv\Reader;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;


/**
 * Reads and writes to and from the specific csv
 *
 * @package Pid\Mapper\Service
 */
class CsvService {

    protected $uploadDir;

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

    public function getColumns($dataset)
    {
        $csv = $this->read($dataset);
        return $csv->fetchOne();
    }

    public function getAllRows($dataset)
    {
        $csv = $this->read($dataset);

        $csvRows = $csv->setOffset(0)->fetchAll();
        if ($dataset['skip_first_row']) {
            array_shift($csvRows);
        }

        return $csvRows;
    }
}