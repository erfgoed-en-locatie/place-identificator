# TODO for the rewrite

- ALL update queries should of course be PER DATASET!!


- setRecordAsUnmappable should also handle liesIn, number count for doing this on no-results page is still wrong
- I would like to have a field to do an easy search on a different name on the no-results page that just calls the api for one term:
    and with all the dataset liesIn etch taken into account so when the term was "Amisvoirt" I can map still it

- we need a button that displays the row_id form the csv with all the data in it in some sort of a popu or something.

+ need to catch the error for standardize when no csv-records could be found!
+ need to find a way to read CR newLines (windows)




## DEPLOYMENT

- clear all datasets online
- send users an email to configure dataset again


- save standardized query, with or without liesIn Where!
- save the q= query in the db!

- final rewrite:
    - on loading the dataset, see if it is a valid one
        - also store the liesIn field, AND also store a timestamp, and the query to the API with which it was found, status


- this also makes it possible to do less precise searches ....

