# PiD - Place Identificator
 
The tool to IdThisPlace.
A frontend application, built on top of silex, to let users identify and standardize (Dutch historical) place names.

The app uses the API of the Dutch Historical Geocoder as it is being developed in the "Erfgoed & Locatie" project.
 

## INSTALLATION INSTRUCTIONS

1. git clone this repository to a directory
2. In a terminal: move into your directory 
3. Download Composer

    `curl -sS https://getcomposer.org/installer | php`

3. And run `php composer.phar install`
4. Create a database based on the file: `sql/pid.sql`
5. Rename the `./app/config/parameters.php.dist` to parameters.php and change the configuration settings for db, email etc. according to your setup
6. Create a virtual host or point your browser to the location you set up for this site
7. Composers post install script should have created the following dirs and made them writeable for the web server:
    app/storage/cache, app/storage/log, app/storage/uploads
If the last step failed do this manually:
    `sudo chmod 777 app/storage/cache app/storage/log app/storage/uploads`
8. On going to production; change the run mode in `/web/index.php` to use the http_cache 

