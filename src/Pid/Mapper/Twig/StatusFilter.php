<?php

namespace Pid\Mapper\Twig;

use Pid\Mapper\Model\Status;

class StatusFilter extends \Twig_Extension
{
    public function getName() {
        return "formatted_status";
    }

    public function getFilters() {
        return array (
            "formatStatus" => new \Twig_Filter_Method($this, "filter"),
        );
    }

    public function filter($key) {
        $options = Status::getStatusOptions();
        if (isset($options[$key])) {
            return $options[$key];
        }
        return $key;
    }

}