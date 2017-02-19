<?php

/**
 * This script handles conversion of the CodeIgniter language files from PHP arrays
 * to JSON objects for use by our translators, and converts JSON files that we get back
 * into PHP arrays again.
 *
 * The .json files and .php files will be created in the directory from which the script is run.
 *
 * Usage:
 * To prepare JSON files:
 * php language_file_json_conversion.php
 *
 * To decode JSON files back to PHP arrays:
 * php language_file_json_objects.php --decode
 */

$globPath = realpath(dirname(__FILE__)) . '/language/en-us/*.php';
$translated_lang_path = realpath(dirname(__FILE__)) . '/translated-language/';
$lang_path = realpath(dirname(__FILE__)) . '/language/';
$fileExtension = ".json";

if (isset($argv[1]) && $argv[1] == '--decode') {
    $fileExtension = ".php";
    $decode = true;
    $globPath = '*.json';
}

foreach (glob($globPath) as $langFile) {
    $fileText = empty($decode) ? "" : "<?php\n";
    if (!empty($decode)) {
        $json = file_get_contents($langFile);
        if (!$json) {
            die("Error loading $langFile\n");
        }
        $langArray = json_decode($json, true);
        if (!$langArray) {
            die("Error decoding JSON in $langFile\n");
        }

        foreach ($langArray as $key=>$value) {
            $fileText = formatFileText($fileText, [$key], $value);
        }
    }
    else {
        $lang = array();
        include_once($langFile);
        $fileText = json_encode($lang, JSON_UNESCAPED_UNICODE);
    }

    $pathInfo = pathinfo($langFile);
    $filename = $pathInfo['filename'] . $fileExtension;

    if (empty($decode)) {
    	file_put_contents($translated_lang_path."/en-us/".$filename, $fileText);
    } else {
    	file_put_contents($lang_path."/es/".$filename, $fileText);
    	file_put_contents($lang_path."/fr-ca/".$filename, $fileText);
    }
}

function formatFileText($fileText, $keys, $value)
{
    $langLine = null;
    if (gettype($value) == 'array') {
        foreach ($value as $valueKey=>$valueItem) {
            $keysWithValueKey = $keys;
            array_push($keysWithValueKey, $valueKey);
            $langLine = formatFileText($langLine, $keysWithValueKey, $valueItem);
        }

    }
    else {
        $langLine = "\$lang";
        if (gettype($keys) != 'array') {
            die('Internal error generating lang line. ' . print_r($keys, true) . " " . $value) . "\n";
        }

        foreach ($keys as $key) {
            $langLine .= "['$key']";
        }

        $langLine .= " = '" . addSlashes($value) . "';\n";
    }

    return $fileText . $langLine;
}
