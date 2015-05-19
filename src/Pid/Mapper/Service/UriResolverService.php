<?php

namespace Pid\Mapper\Service;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;


/**
 * Resolves the uri's as they were found in Histograph
 * In an attempt to get more detailed data, straight from the sources
 *
 * @package Pid\Mapper\Service
 */
class UriResolverService {


    /**
     * @var array Fields in the API result that hold the data we want to store
     */
    private $fieldsOfInterest = array(
        'label', 'lon', 'lat'
    );

    public function __construct()
    {
    }

    /**
     * Faster alternative for file_get_contents
     *
     * @param $url
     * @param array $opts
     * @return mixed
     */
    public function http_get_contents($url, $opts = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        //curl_setopt($ch, CURLOPT_USERAGENT, "{$_SERVER['SERVER_NAME']}");
        curl_setopt($ch, CURLOPT_URL, $url);
        if(is_array($opts) && $opts) {
            foreach($opts as $key => $val) {
                curl_setopt($ch, $key, $val);
            }
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if(FALSE === ($retval = curl_exec($ch))) {
            error_log(curl_error($ch));
        } else {
            return $retval;
        }
    }
    /**
     * Try to find the details for
     *
     * @param string $uri
     * @return array The array contains hits|data keys
     * @throws \Exception
     */
    public function findOne($uri)
    {

        if (preg_match("/vocab.getty.edu\/tgn/", $uri)) {
            $response = $this->findTGN($uri);
        } elseif (preg_match("/geonames.org/", $uri)) {
            $response = $this->findGeonames($uri);
        } elseif (preg_match("/gemeentegeschiedenis.nl\/gemeentenaam/", $uri)) {
            $response = $this->findGemeentegeschiedenis($uri);
        }
        if ($response) {
            return $this->transformResponse($response);
        }
        throw new \RuntimeException('The uri resolver could not resolve that location');
    }

    public function findTGN($uri)
    {
        require_once (__DIR__ . '/../../../../bin/sparqllib.php');
        $db = sparql_connect( "http://vocab.getty.edu/sparql" );

        if( !$db ) {
            throw new \RuntimeException('The TGN service is unreachable and returns the follwing error: ' . $db->error());
        }

        $db->ns( "aat","http://vocab.getty.edu/aat/" );
        $db->ns( "foaf","http://xmlns.com/foaf/0.1/" );
        $db->ns( "gvp","http://vocab.getty.edu/ontology#" );
        $db->ns( "skos","http://www.w3.org/2004/02/skos/core#" );
        $db->ns( "tgn","http://vocab.getty.edu/tgn/" );
        $db->ns( "wgs","http://www.w3.org/2003/01/geo/wgs84_pos#" );
        $db->ns( "xl","http://www.w3.org/2008/05/skos-xl#" );

        $sparql =
            'select ?label ?lat ?lon ?parent {
			<' . $uri . '> foaf:focus ?focus.
			?focus wgs:lat ?lat .
			?focus wgs:long ?lon .
			<' . $uri . '> gvp:parentString ?parent .
			<' . $uri . '> rdfs:label ?label
		}';

        $result = $db->query( $sparql );
        if( !$result ) { print "Fout " . $db->errno() . ": " . $db->error(). "\n"; exit; }

        $data = array(	"uri" => $uri,
            "lat" => $result->rows[0]['lat']['value'],
            "lon" => $result->rows[0]['lon']['value']
        );
        $data['label'] = array();

        // "parent" => $result->rows[0]['parent']['value']

        // get country & evt. province from parentstring
        $parents = explode(",", $result->rows[0]['parent']['value']);
        $parents = array_reverse($parents);

        $data['country'] = trim($parents[2]);
        if($country="Nederland"){
            $data['province'] = trim($parents[3]);
        }
        foreach($result->rows as $row){
            $data['label'][] = $row['label']['value'];
        }
        return $data;
    }


    public function findGeonames($uri)
    {
        $id = str_replace("http://www.geonames.org/", "", $uri);

        $json = $this->http_get_contents("http://ws.geonames.org/getJSON?geonameId=" . $id . "&username=mmmenno");
        // todo fix username mmmenno
        $found = json_decode($json,true);

        $data = array(	"uri" => $uri,
            "lat" => $found['lat'],
            "lon" => $found['lng'],
            "country" => $found['countryName']
        );
        $data['label'] = array($found['toponymName']);

        if($found['countryCode']=="NL"){
            $data['province'] = $found['adminName1'];
        }

        if(substr($found['fcode'],0,1) == "P"){
            $data['type'] = "hg:Place";
        }elseif($found['fcode'] == "ADM2" && $found['countryCode']=="NL"){
            $data['type'] = "hg:Municipality";
        }

        return $data;
    }

    public function findGemeentegeschiedenis($uri)
    {
        $jsonuri = str_replace("gemeentenaam/","gemeentenaam/json/",$uri);
        $json = file_get_contents($jsonuri);

        $found = json_decode($json, true);

        $data = array(	"uri" => $uri,
            "geometries" => $found['geometries'],
            "type" => "hg:Municipality"
        );
        $data['label'] = array($found['name']);
        return $data;
    }

    /**
     * Transform the response to a storable json string
     * @param array $response
     * @return string
     */
    public function transformResponse($response)
    {
        // todo fix geometry for gg ... but which one to store?? We get one for every year a border changed
        $data['name'] = $response['label'][0];
        $data['uri'] = $response['uri'];
        if (isset($response['lat']) && isset($response['lon'])) {
            $data['geometry']['type'] = 'Point';
            $data['geometry']['coordinates'] = array($response['lat'], $response['lon']);
        }
        return json_encode($data);
    }

}