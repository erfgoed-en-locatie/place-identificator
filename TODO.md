# TODO for the rewrite


- save standardized query, with or without liesIn Where!

- final rewrite:
    - on loading the dataset, see if it is a valid one
    - insert all records into the records table and on standardizing, always do an UPDATE
        - also store the liesIn field, AND also store a timestamp, and the query to the API with which it was found, status
        - no longer save the test results but simply show the on a separate page

    - on standardizing, always use records from db, where status != 1
    - make a separate "remove previous mapping"- button

- this also makes it possible to do less precise searches ....

- set mappings and dataset details in one table, maybe, to simplify

- should be able to keep earlier (matched/unmatched records
    (dataset_Service clearRecordsForDataset should work with status)


- should do better modelling:
    - place_column and lies_in_column should be in dataset table
    - field_mapping table should be rebamed search_options and only hold the data thats repeatable (maybe more than one row?



- batches?