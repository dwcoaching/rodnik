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

if (! function_exists('mb_ucfirst')) {
    function mb_ucfirst($string, $encoding = 'UTF-8')
    {
        $firstChar = mb_substr($string, 0, 1, $encoding);
        $then = mb_substr($string, 1, null, $encoding);
        return mb_strtoupper($firstChar, $encoding) . $then;
    }
}

function without_http($string)
{
    return preg_replace('/https?:\/\/(www\.)?/', '', $string);
}

function duo_route($parameters = [])
{
    $baseUrl = route('duo');
    
    if (empty($parameters)) {
        return $baseUrl;
    }
    
    // Only include parameters that differ from defaults
    $defaults = config('duo.url_defaults');
    $pageParams = array_filter($parameters, function($value, $key) use ($defaults) {
        return !isset($defaults[$key]) || $defaults[$key] !== $value;
    }, ARRAY_FILTER_USE_BOTH);
    
    $queryString = http_build_query(['page' => $pageParams], '', '&', PHP_QUERY_RFC1738);
    $queryString = urldecode($queryString);
    return $baseUrl . '?' . $queryString;
}
