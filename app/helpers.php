<?php

function decline_number($value, $strings, $withNumber = true)
{
    if ($withNumber) {
        $result = $value . ' ';
    } else {
        $result = '';
    }

    if ($value > 100) {
        $value = $value % 100;
    }

    $firstDigit = $value % 10;
    $secondDigit = floor($value / 10);

    if ($secondDigit != 1) {
        if ($firstDigit == 1) {
            $result .= $strings[0];
        } elseif ($firstDigit > 1 && $firstDigit < 5) {
            $result .= $strings[1];
        } else {
            $result .= $strings[2];
        }
    } else {
        $result .= $strings[2];
    }

    return $result;
}

function mb_ucfirst($string, $encoding = 'UTF-8')
{
    $firstChar = mb_substr($string, 0, 1, $encoding);
    $then = mb_substr($string, 1, null, $encoding);
    return mb_strtoupper($firstChar, $encoding) . $then;
}

function without_http($string)
{
    return preg_replace('/https?:\/\/(www\.)?/', '', $string);
}
