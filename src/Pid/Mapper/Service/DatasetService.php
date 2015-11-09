<?php

namespace Pid\Mapper\Service;


use Pid\Mapper\Model\DatasetStatus;
use Pid\Mapper\Model\Status;


/**
 * Service or manager for the Dataset data
 *
 * @package Pid\Mapper\Service
 */
class DatasetService
{

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    /**
     * @var CsvService
     */
    private $csvService;

    public function __construct($db, $csvService)
    {
        $this->db = $db;
        $this->csvService = $csvService;
    }

    /**
     * Fetch a dataset by id and optionally also check on User
     *
     * @param $id
     * @param null $userId
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchDataset($id, $userId = null)
    {
        if (null === $userId) {
            $stmt = $this->db->executeQuery('SELECT * FROM datasets WHERE id = :id', array(
                'id' => (int)$id
            ));
        } else {
            $sql = 'SELECT * FROM datasets WHERE id = :id AND user_id = :userId';
            $params = array(
                'id'     => (int)$id,
                'userId' => (int)$userId
            );
            $stmt = $this->db->executeQuery($sql, $params);
        }

        return $stmt->fetch();
    }

    /**
     * Fetch by status
     *
     * @param $id
     * @param $status
     * @return bool|string
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchCountForDatasetWithStatus($id, $status)
    {
        $sql = '
          SELECT COUNT(*) AS aantal
          FROM records
          WHERE dataset_id = :id
          AND status IN (' . implode(",", $status) . ')
          ';
        $params = array(
            'id' => (int)$id
        );
        $stmt = $this->db->executeQuery($sql, $params);

        return $stmt->fetchColumn(0);
    }

    public function fetchRecsWithStatus($id, $status)
    {
        $sql = 'SELECT * FROM records WHERE dataset_id = :id AND status IN (' . implode(",", $status) . ')';
        $params = array(
            'id' => (int)$id
        );
        $stmt = $this->db->executeQuery($sql, $params);

        return $stmt->fetchAll();
    }

    public function fetchRec($recid)
    {
        $sql = 'SELECT * FROM records WHERE id = :id';
        $params = array(
            'id' => (int)$recid
        );
        $stmt = $this->db->executeQuery($sql, $params);

        return $stmt->fetchAll();
    }

    public function fetchRecordByName($name)
    {
        $sql = 'SELECT * FROM records WHERE original_name = :name';
        $params = array(
            'name' => $name
        );
        $stmt = $this->db->executeQuery($sql, $params);

        return $stmt->fetch();
    }

    public function fetchRecordByRowId($rowId)
    {
        $sql = 'SELECT * FROM records WHERE row_id = :row';
        $params = array(
            'row' => $rowId
        );
        $stmt = $this->db->executeQuery($sql, $params);

        return $stmt->fetch();
    }

    public function fetchRecs($setid)
    {
        $sql = 'SELECT * FROM records WHERE dataset_id = :id';
        $params = array(
            'id' => (int)$setid
        );
        $stmt = $this->db->executeQuery($sql, $params);

        return $stmt->fetchAll();
    }

    /**
     * Clear the results for a record
     *
     * @param integer $id
     * @return int
     * @internal param $data
     */
    public function clearRecord($id)
    {
        $data['status'] = Status::MAPPED_EXACT_NOT_FOUND;
        $data['hg_id'] = null;
        $data['hg_uri'] = null;
        $data['hg_name'] = null;
        $data['hg_geometry'] = null;
        $data['hits'] = 0;

        return $this->db->update('records', $data, array('id' => $id));
    }

    /**
     * Mark the record as unmappable so a user can skip it in the interface
     *
     * @param $id
     * @return int
     */
    public function setRecordAsUnmappable($id)
    {
        $data['status'] = Status::UNMAPPABLE;
        $data['hg_id'] = null;
        $data['hg_uri'] = null;
        $data['hg_name'] = null;
        $data['hg_geometry'] = null;
        $data['hits'] = 0;

        // fetch original name, so we can update all records with the same name
        $stmt = $this->db->executeQuery('SELECT original_name as name, dataset_id FROM records WHERE id = :id', array(
            'id' => (int)$id
        ));
        $stored = $stmt->fetch();

        // also fetch ids with the same original name, in order to be able to delete the rows in the ajaxy interface
        $statement = $this->db->executeQuery('SELECT id FROM records WHERE original_name = ?', array($stored['name']));
        $ids = $statement->fetchAll();

        $this->db->update('records', $data, array('original_name' => $stored['name']));

        return $ids;
    }

    /**
     * Store the provided mapping for the csv
     *
     * @param array $data
     * @return int
     */
    public function storeFieldMapping($data)
    {
        $date = new \DateTime('now');
        $data['updated_on'] = $date->format('Y-m-d H:i:s');
        $data['status'] = DatasetStatus::STATUS_FIELDS_MAPPED;

        return $this->db->update('datasets', $data, array(
            'id' => $data['id']
        ));
    }

    /**
     * Copies all the records from teh csv to the db, if that was not done already
     *
     * @param $dataset
     * @return bool
     */
    public function copyRecordsFromCsv($dataset)
    {
        $stm = $this->db->query("SELECT * FROM records WHERE dataset_id = {$dataset['id']} LIMIT 1");
        if ($stm->fetch()) {
            return true;
        }

        $rows = $this->csvService->getRows($dataset);
        foreach ($rows as $rowId => $row) {
            $data['dataset_id'] = $dataset['id'];
            $data['row_id'] = $rowId;

            $data['original_name'] = $row[$dataset['placename_column']];
            if (isset($dataset['liesin_column'])) {
                $data['liesin_name'] = $row[$dataset['liesin_column']];
            }
            $data['status'] = Status::UNMAPPED;

            $this->storeRecord($data);
        }

        return true;
    }

    /**
     * Fetches all records that have not been standardized already
     *
     * @param $datasetId
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchRecordsToStandardize($datasetId)
    {
        $sql = 'SELECT * FROM records WHERE dataset_id = :id AND status NOT IN (:status)';
        $params = array(
            'id'     => (int) $datasetId,
            'status' => implode(',', [
                    Status::MAPPED_EXACT,
                    Status::MAPPED_MANUALLY,
                    Status::UNMAPPABLE
                ]
            )
        );
        $stmt = $this->db->executeQuery($sql, $params);

        return $stmt->fetchAll();
    }

    /**
     * Store the csv properties for which reading the csv is not necessary
     *
     * @param $data
     * @return int
     */
    public function storeCSVConfig($data)
    {
        $date = new \DateTime('now');
        $data['updated_on'] = $date->format('Y-m-d H:i:s');

        return $this->db->update('datasets', $data, array(
            'id' => $data['id']
        ));
    }

    public function setMappingStarted($datasetId)
    {
        return $this->db->update('datasets', array('status' => DatasetStatus::STATUS_BEING_MAPPED), array(
            'id' => $datasetId
        ));
    }

    public function setMappingFinished($datasetId)
    {
        return $this->db->update('datasets', array('status' => DatasetStatus::STATUS_MAPPED), array(
            'id' => $datasetId
        ));
    }

    public function setMappingFailed($datasetId)
    {
        return $this->db->update('datasets', array('status' => DatasetStatus::STATUS_MAPPING_FAILED), array(
            'id' => $datasetId
        ));
    }


    public function getUser($id)
    {
        $stmt = $this->db->executeQuery('SELECT name, email FROM users WHERE id = :id', array(
            'id' => (int)$id
        ));

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Store a standardized record
     *
     * @param array $data
     * @return int
     */
    public function storeRecord($data)
    {
        $date = new \DateTime('now');
        $data['created_on'] = $date->format('Y-m-d H:i:s');

        return $this->db->insert('records', $data);
    }

    /**
     * Save the manually mapped record
     * Will now update all records with the same original name
     *
     * @param array $data
     * @param integer $id
     * @return int
     */
    public function storeManualMapping($data, $id)
    {
        $crowdData = $data;

        $date = new \DateTime('now');
        $data['updated_on'] = $date->format('Y-m-d H:i:s');
        $data['status'] = Status::MAPPED_MANUALLY;
        $data['hits'] = 1;

        // fetch original name, so we can update all records with the same name
        $stmt = $this->db->executeQuery('SELECT original_name as name, dataset_id FROM records WHERE id = :id', array(
            'id' => (int)$id
        ));
        $stored = $stmt->fetch();

        // also save the mapping into the crowd table
        $crowdData['created_on'] = $date->format('Y-m-d H:i:s');
        $crowdData['original_name'] = $stored['name'];
        $crowdData['dataset_id'] = $stored['dataset_id'];

        $this->db->insert('crowd_mapping', $crowdData);

        // grmbl, need to fetch the ids first, in order to be able to delete the rows in the ajaxy interface
        $statement = $this->db->executeQuery('SELECT id FROM records WHERE original_name = ?', array($stored['name']));
        $ids = $statement->fetchAll();

        $this->db->update('records', $data, array('original_name' => $stored['name']));

        return $ids;
    }

    /**
     * Clear records for a dataset, AND wit ha specific status if provided
     *
     * @param $datasetId
     * @return int
     */
    public function clearRecordsForDataset($datasetId)
    {
        $this->db->update('datasets', array('status' => DatasetStatus::STATUS_NEW), array(
            'id' => $datasetId
        ));

        return $this->db->delete('records', array('dataset_id' => $datasetId));
    }

    /**
     * Store the geocoded result
     *
     * @param array $data
     * @return bool
     */
    public function storeGeocodedRecords($data)
    {
        foreach ($data as $row) {
            $this->updateRecord($row);
        }

        return true;
    }

    public function updateRecord($data)
    {
        $date = new \DateTime('now');
        $data['updated_on'] = $date->format('Y-m-d H:i:s');

        // if search was with liesIn, the update should be more specific
        if (strlen($data['liesin_name']) > 0) {
            $this->db->update('records', $data, array(
                'original_name' => $data['original_name'],
                'liesin_name' => $data['liesin_name']
            ));
        } else {
            $this->db->update('records', $data, array(
                'original_name' => $data['original_name']
            ));
        }
    }


}