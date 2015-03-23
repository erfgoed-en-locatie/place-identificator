<?php

namespace Pid\Mapper\Twig;



use Pid\Mapper\Service\GeocoderService;

class SearchOptionFilter extends \Twig_Extension
{
    public function getName() {
        return "formatted_search_option";
    }

    public function getFilters() {
        return array (
            "formatSearchOption" => new \Twig_Filter_Method($this, "filter"),
        );
    }

    public function filter($key) {
        $options = GeocoderService::$searchOptions;
        if (isset($options[$key])) {
            return $options[$key];
        }
        return $key;
    }

}