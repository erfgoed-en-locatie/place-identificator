<?php

namespace Pid;

class htmlTable
{

    public static function createTable($result, $id = 'data', $class = 'table') {
        $html = '<table id="' . $id . '" class="' . $class . '">';
        $html.= self::createTableHeader($result[0]);
        foreach($result as $row) {
            $html.= '<tbody><tr>';
            foreach($row as $field => $value) {
                $html.= '<td>' . $value . '</td>';
            }
            $html.= '</tr><tbody>';
        }
        $html.='</table>';
        return $html;
    }

    private static function createTableHeader($row) {
        $html = '<thead><tr>';
        foreach($row as $name =>$value) {
            $html.= '<th>' . $name . '</th>';
        }
        $html.= '</tr></thead>';
        return $html;
    }

}
