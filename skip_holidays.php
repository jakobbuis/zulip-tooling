<?php

use League\Csv\Reader;

// Skip operation on national holidays
$path = __DIR__ . '/holidays.csv';
if (is_readable($path)) {
    $csv = Reader::from($path);
    $csv->setHeaderOffset(0);
    $holidays = array_map(fn ($line) => $line['date'], iterator_to_array($csv->getRecords()));

    if (in_array(date('Y-m-d'), $holidays)) {
        echo "Today is a national holiday. Skipping operation.\n";
        exit;
    }
}
