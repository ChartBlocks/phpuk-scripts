<?php

chdir(__DIR__ . '/../');

require('vendor/autoload.php');
$config = require('config/local.php');

$authentication_tokens = array(
    'app_key' => $config['eventbrite']['appKey'],
    'user_key' => $config['eventbrite']['userKey']
);

$eb = new Php2014\EventBrite($authentication_tokens);
$result = $eb->event_list_attendees(array(
    'id' => $config['eventbrite']['eventId'],
    'status' => 'attending',
    'only_display' => 'last_name,address,profile,company,age,gender,barcodes',
    'show_full_barcodes' => 'true'
        ));

$data = array();

$thursday = strtotime('2016-02-18');
$friday = strtotime('2016-02-19');

//$checkIns = array(
//    strtotime('2013-02-21 07:00'),
//    strtotime('2013-02-21 08:00'),
//    strtotime('2013-02-21 09:15'),
//    strtotime('2013-02-21 10:00'),
//    strtotime('2013-02-21 11:45'),
//    strtotime('2013-02-22 07:00'),
//    strtotime('2013-02-22 08:00'),
//    strtotime('2013-02-22 09:30'),
//    strtotime('2013-02-22 10:00'),
//    strtotime('2013-02-22 11:00'),
//);

foreach ($result->attendees as $object) {
    $attendee = $object->attendee;

    $date = null;
    $floor = 0;

//    $date = $checkIns[array_rand($checkIns)];

    foreach ($attendee->barcodes as $barcodeObject) {
        $barcode = $barcodeObject->barcode;
        if ((string) $barcode->status == 'used') {
            $date = strtotime($barcode->date_modified) + 8 * 3600;
            break;
        }
    }

    if ($date) {
        $floor = floor($date / 900) * 900;
    }

    $data[$attendee->id] = array(
        'lastname' => $attendee->last_name,
        'country' => empty($attendee->work_country_code) ? 'n/a' : $attendee->work_country_code,
        'company' => empty($attendee->company) ? 'n/a' : $attendee->company,
        'age' => empty($attendee->age) ? 'n/a' : $attendee->age,
        'gender' => empty($attendee->gender) ? 'n/a' : $attendee->gender,
        'checkedInThursday' => ($floor >= $thursday && $floor < $thursday + 86400) ? 1 : null,
        'checkedInFriday' => ($floor >= $friday && $floor < $friday + 86400) ? 1 : null,
        'checkedInAt' => date('H:i', $floor),
        'i' => 1
    );
}

ksort($data);

//foreach ($data as $row) {
//    $row = array_map(function($value) {
//        return '"' . $value . '"';
//    }, $row);
//
//    echo implode(',', $row) . PHP_EOL;
//}


$cb = new ChartBlocks\Client(array(
    'token' => $config['chartblocks']['token'],
    'secret' => $config['chartblocks']['secret'],
        ));

$set = $cb->getRepository('dataSet')->findById($config['chartblocks']['attendeeSetId']);

$r = 2;
$rowSet = new \ChartBlocks\DataSet\RowSetDynamic($set);
foreach ($data as $person) {
    $row = new \ChartBlocks\DataSet\Row($set, array('row' => $r));

    $c = 1;
    foreach ($person as $key => $value) {
        $row->setCell($c, $value);
        $c++;
    }

    $rowSet->addRow($row);
    $r++;
}

$rowSet->save();
