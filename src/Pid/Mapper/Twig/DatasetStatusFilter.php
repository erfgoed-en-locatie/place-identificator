<?php

namespace Pid\Mapper\Twig;

use Pid\Mapper\Model\DatasetStatus;

class DatasetStatusFilter extends \Twig_Extension
{
    public function getName() {
        return "formatted_dataset_status";
    }

    public function getFilters() {
        return array (
            "formatDatasetStatus" => new \Twig_Filter_Method($this, "filter"),
        );
    }

    public function filter($key) {
        $options = DatasetStatus::getStatusOptions();
        if (isset($options[$key])) {
            return $options[$key];
        }
        return $key;
    }

}