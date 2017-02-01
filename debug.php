<?php
require 'vendor/autoload.php';

$longOpts  = array(
    "startDate::",
    "endDate::",
    "concurrency::",
    "maxResultsPerAuthor::",
    "wait::",
);
$options = getopt(null, $longOpts);

$scrapper = new \aivanouski\GrbjScrapper();
foreach($options as $attribute => $value) {
    $scrapper->$attribute = $value;
}
print_r($scrapper->parse());