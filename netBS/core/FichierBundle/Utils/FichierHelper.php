<?php

namespace NetBS\FichierBundle\Utils;

class FichierHelper
{
    /**
     * @param $class
     * @param bool $flip
     * @return array
     */
    public static function getStatutChoices($class, $flip = false) {

        $choices = call_user_func([$class, 'getStatutChoices']);
        return $flip ? array_flip($choices) : $choices;
    }

    /**
     * @param $class
     * @param bool $flip
     * @return array
     */
    public static function getValidityChoices($class, $flip = false) {

        $choices = call_user_func([$class, 'getValidityChoices']);
        return $flip ? array_flip($choices) : $choices;
    }

    public static function objectToArray($obj)
    {
        if(is_object($obj)) $obj = (array) $obj;
        if(is_array($obj)) {
            $new = array();
            foreach($obj as $key => $val) {
                $keyData = explode("\x00", $key);
                $new[end($keyData)] = self::objectToArray($val);
            }
        }
        else $new = $obj;
        return $new;
    }

    public static function arrayToString($arr, $depth = 0) {
        $pad = function($p) {
            $ttt = '';
            for($i = 0; $i < $p; $i++) $ttt .= '    ';
            return $ttt;
        };

        $txt = '';
        foreach($arr as $key => $value) {
            if (is_array($value)) {
                $txt .= $pad($depth) . $key . " : [<br/>";
                $txt .= self::arrayToString($value, $depth + 1);
                $txt .= $pad($depth) . "]</br>";
            } elseif ($value !== null) {
                $txt .= $pad($depth) . $key . " : " . $value . "<br/>";
            }
        }
        return $txt;
    }
}
