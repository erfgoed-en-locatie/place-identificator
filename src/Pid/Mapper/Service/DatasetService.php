<?php

namespace Pid\Mapper\Service;
use Pid\Mapper\Model\DatasetStatus;
use Pid\Mapper\Model\Status;


/**
 * Service or manager for the Dataset data
 *
 * @package Pid\Mapper\Service
 */
class DatasetService {

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
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
                'id' => (int)$id,
                'userId' => (int)$userId);
            $stmt = $this->db->executeQuery($sql, $params);
        }
        return $stmt->fetch();
    }

    /**
     * Fetch dataset details with Fieldmappings
     *
     * @param $id
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function fetchDatasetDetails($id)
    {
        $stmt = $this->db->executeQuery('
          SELECT d.*, f.hg_type, f.hg_dataset, f.geometry
          FROM datasets d
          LEFT JOIN field_mapping f ON f.dataset_id = d.id
          WHERE d.id = :id', array(
            'id' => (int)$id
        ));

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
          AND status IN (' . implode(",",$status) . ')
          ';
        $params = array(
            'id' => (int) $id
        );
        $stmt = $this->db->executeQuery($sql, $params);
        return $stmt->fetchColumn(0);
    }

    public function fetchFieldmappingForDataset($id)
    {
            $stmt = $this->db->executeQuery('SELECT * FROM field_mapping WHERE dataset_id = :id', array(
                'id' => (int) $id
            ));
        return $stmt->fetch();
    }

    public function fetchRecsWithStatus($id,$status)
    {
        $sql = 'SELECT * FROM records WHERE dataset_id = :id AND status IN (' . implode(",",$status) . ')';
        $params = array(
            'id' => (int)$id);
        $stmt = $this->db->executeQuery($sql, $params);
        
        return $stmt->fetchAll();
    }

    public function fetchRec($recid)
    {
        $sql = 'SELECT * FROM records WHERE id = :id';
        $params = array(
            'id' => (int)$recid);
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

    public function fetchRecs($setid)
    {
        $sql = 'SELECT * FROM records WHERE dataset_id = :id';
        $params = array(
            'id' => (int)$setid);
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
            'id' => (int) $id
        ));
        $stored = $stmt->fetch();

        // also fetch ids with the same original name, in order to be able to delete the rows in the ajaxy interface
        $statement = $this->db->executeQuery('SELECT id FROM records WHERE original_name = ?', array($stored['name']));
        $ids = $statement->fetchAll();

        $this->db->update('records', $data, array('original_name' => $stored['name']));
        return $ids;
    }

    /**
     * Save the provided mapping or update if it already exists
     * Also update the status of the dataset to mapped
     *
     * @param array $data
     * @return int
     */
    public function storeFieldMapping($data)
    {
        $date = new \DateTime('now');
        $this->db->update('datasets', array('status' => DatasetStatus::STATUS_FIELDS_MAPPED), array(
            'id' => $data['dataset_id'],
            'status' => DatasetStatus::STATUS_NEW
        ));

        if ($this->fetchFieldmappingForDataset($data['dataset_id'])) {
            $data['updated_on'] = $date->format('Y-m-d H:i:s');
            return $this->db->update('field_mapping', $data, array('dataset_id' => $data['dataset_id']));
        }

        $data['created_on'] = $date->format('Y-m-d H:i:s');
        return $this->db->insert('field_mapping', $data);
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

    /**
     * Fetch the user supplied configs for this dataset
     *
     * @param $id
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function getFieldMappingForDataset($id)
    {
        $stmt = $this->db->executeQuery('SELECT * FROM field_mapping WHERE dataset_id = :id', array(
            'id' => (int)$id
        ));
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function getUser($id)
    {
        $stmt = $this->db->executeQuery('SELECT name, email FROM users WHERE id = :id', array(
            'id' => (int)$id
        ));
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Save the provided mapping
     *
     * @param array $data
     * @return int
     */
    public function storeMappedRecord($data)
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
            'id' => (int) $id
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
     * @param null $status
     * @return int
     */
    public function clearRecordsForDataset($datasetId, $status = null)
    {
        return $this->db->delete('records', array('dataset_id' => $datasetId));
    }

    /**
     * Store the geocoded result
     *
     * If the data set was standardized before, als delete all old mappings
     * @param array $data
     * @return bool
     */
    public function storeGeocodedRecords($data)
    {
        foreach($data as $row) {
            $this->storeMappedRecord($row);
        }

        return true;
    }


}