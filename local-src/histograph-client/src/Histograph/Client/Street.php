<?php

namespace Histograph\Client;

class Street extends Client
{

    /**
     * Call API for one place name and try to find as much info as possible
     *
     * @param $name
     * @return array The array contains hits|data keys
     */
    public function searchStreetsInPlace($name)
    {
        $response = $this->search($name);
        if ($response->getStatusCode() === 200) {
            return $this->handleResponse($response->json(array('object' => true)));
        }
    }
    // todo create methods for getting only streets from the repsonse

    public function searchForPlacesInAProvince()
    {

    }

    // todo create intelligent Search, first as strict asp possible... -> towards more lenient

    public function searchVeryStrict($name)
    {
        $response = $this->setExact(true)
            ->setQuoted(true)
            ->setGeometry(true)
            ->setSearchType(Client::SEARCH_STREETS)
            ->search($name);
    }
}
