<?php

namespace Ovesco\FacturationBundle\Util;

class QrReference
{
    public static function getMatrix() {
        return [
            [0,9,4,6,8,2,7,1,3,5],
            [9,4,6,8,2,7,1,3,5,0],
            [4,6,8,2,7,1,3,5,0,9],
            [6,8,2,7,1,3,5,0,9,4],
            [8,2,7,1,3,5,0,9,4,6],
            [2,7,1,3,5,0,9,4,6,8],
            [7,1,3,5,0,9,4,6,8,2],
            [1,3,5,0,9,4,6,8,2,7],
            [3,5,0,9,4,6,8,2,7,1],
            [5,0,9,4,6,8,2,7,1,3]
        ];
    }

    public static function getCheckDigit($refNumber) {

        $control = 0;
        $matrix = self::getMatrix();
        for($i = 0; $i < strlen($refNumber); $i++) $control = $matrix[$control][$refNumber[$i]];
        return [0,9,8,7,6,5,4,3,2,1][$control];
    }
}