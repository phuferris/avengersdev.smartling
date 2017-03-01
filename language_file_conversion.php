<?php

/**
 * This script handles conversion of the CodeIgniter language files from PHP arrays
 * to JSON objects for use by our translators, and converts JSON files that we get back
 * into PHP arrays again.
 *
 * When running this script under "encode", the English lang files from  Squirrel repo application/language/en-us folder
 * will converted into .json files, which are stored at Smartling repo translating/en folder to be submitted
 * to Smartling API for translation
 *
 * When running this script under "decode",  the Spanish and French .json files Smartling repo translated/es and translated/fr-ca
 * will be converted into .php file and placed into Squirrel repo application/language/es and application/language/fr-ca
 * folder.
 *
 * Usage:
 *
 * php language_file_conversion.php and enter the correct input when it is prompted.
 *
 * Note: This script needs to be run within CrowdRise Smartling Repository
 */

// Please modify this path to the Squirrel language folder on local before running this script
$pathToLanguageFolder = "/var/www/sites/avengersdev.smartling/language";

$validAction = false;
do {
    echo "\n\nDo you want to encode or decode the lang files (encode/decode): ";
    $action = trim(fgets(STDIN));

    if (!in_array($action, ['encode', 'decode'])) {
        echo "\n\nPlease enter \"encode\" to convert php language files into json string files.";
        echo "\nEnter \"decode\" to convert json string files back into php language files";
    } else {
        $validAction = true;
    }
} while (!$validAction);

if ($action == "encode") {
    encode_lang_files($pathToLanguageFolder);
} else {
    decode_lang_files($pathToLanguageFolder);
}

/**
 * Convert all .php file from Squirrel repo application/language/en-us folder
 * to .json files and put them in Smartling Repo translating/en-us
 *
 * @param string $pathToLanguageFolder path to the language folder in Squirrel repo on local
 */
function encode_lang_files($pathToLanguageFolder)
{
    $globPath = $pathToLanguageFolder . "/en-us/*.php";

    foreach (glob($globPath) as $langFile) {
        $lang = array();
        include_once($langFile);
        $fileText = json_encode($lang, JSON_UNESCAPED_UNICODE);

        $pathInfo = pathinfo($langFile);
        $filename = $pathInfo['filename'] . ".json";
        file_put_contents("translating/en-us/".$filename, $fileText);
    }

    echo "\n\n\nCongratulations. All .php files from ".$pathToLanguageFolder. " folder have been converted into .json files and placed into translating/en-us folder";
    echo "\n\nNote: Please create a MR for to check if any change has been made.";
    echo "\nMerging the changes into master branch will trigger Smartling repo-connector to submit them to Smartling API for translation\n\n\n";
}

/**
 * Convert all .json translated lang files from Smartling repo translated/es and translated/fr-ca
 * to .php files and put them into appropriate lang folder application/language/es or application/language/fr-ca
 *
 * @param string $pathToLanguageFolder $pathToLanguageFolder path to the language folder in Squirrel repo on local
 */
function decode_lang_files($pathToLanguageFolder)
{
    $supporting_locales = ['es', 'fr-ca'];

    foreach ($supporting_locales as $locale ) {

        $globPath = "translated/" . $locale . "/*.json";

        $fileText = "<?php\n";
        foreach (glob($globPath) as $langFile) {

            if (!file_exists($langFile)) {
                die("\n\n" . $langFile . " file does not exist. Please check.");
            }

            $json = file_get_contents($langFile);
            if (!$json) {
                die("\n\nError loading $langFile\n");
            }

            $langArray = json_decode($json, true);
            if (!$langArray) {
                die("\n\nError decoding JSON in $langFile\n");
            }

            foreach ($langArray as $key => $value) {
                $fileText = formatFileText($fileText, [$key], $value);
            }

            $pathInfo = pathinfo($langFile);
            $filename = $pathInfo['filename'] . ".php";

            file_put_contents($pathToLanguageFolder . "/" . $locale . "/" . $filename, $fileText);
        }
    }

    echo "\n\n\nCongratulations. All .json translated lang files from translated/es and translated/fr-ca folder have been converted";
    echo "to .php files and placed into ".$pathToLanguageFolder;
    echo "\n\nNote: Please create a MR for to check if any change has been made.\n\n\n";
}

/**
 * @param $fileText
 * @param $keys
 * @param $value
 * @return string
 */
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
