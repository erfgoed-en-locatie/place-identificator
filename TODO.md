# TODO for the rewrite

- ALL update queries should of course be PER DATASET!!
- use DISTINCT queries for fetchRecordsToStandardize (with and without liesIn)
so we don't update all rows per cached item again and again....
 OR skip the update result table when a record was found in the cache

## DEPLOYMENT

- clear all datasets online
- send users an email to configure dataset again



- setRecordAsUnmappable should also handle liesIn
- save standardized query, with or without liesIn Where!
- save the q= query in the db!

- final rewrite:
    - on loading the dataset, see if it is a valid one
        - also store the liesIn field, AND also store a timestamp, and the query to the API with which it was found, status


- this also makes it possible to do less precise searches ....

