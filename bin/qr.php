<?php

chdir(__DIR__ . '/../');

use JeroenDesloovere\VCard\VCard;
require('vendor/autoload.php');
$config = require('config/local.php');

$authentication_tokens = array(
    'app_key' => $config['eventbrite']['appKey'],
    'user_key' => $config['eventbrite']['userKey']
);

$eb = new Php2014\EventBrite($authentication_tokens);

$event = $eb->event_get(array('id' => $config['eventbrite']['eventId']));
$tickets = array();
foreach ($event->event->tickets as $ticketObject) {
    $ticket = $ticketObject->ticket;
    $tickets[$ticket->id] = $ticket;
}

$result = $eb->event_list_attendees(array(
    'id' => $config['eventbrite']['eventId'],
    'status' => 'attending',
    'show_full_barcodes' => 'true',
//    'count' => 10
        ));

$data = array();

foreach ($result->attendees as $object) {
    $attendee = $object->attendee;

    $barcode = null;
    foreach ($attendee->barcodes as $barcodeObject) {
        $barcode = $barcodeObject->barcode->id;
    }

    // define vcard
    $vcard = new VCard();

    // add personal data
    $vcard->addName($attendee->last_name, $attendee->first_name);

    // add work data
    $vcard->addCompany($attendee->company);
    $vcard->addJobtitle($attendee->job_title);
    $vcard->addEmail($attendee->email);
    $vcard->addPhoneNumber($attendee->work_phone, 'PREF;WORK');
    $vcard->addPhoneNumber($attendee->work_phone, 'WORK');
    $vcard->addURL($attendee->website);

    $numberFilename = __DIR__ . '/../qr/' . $attendee->id . '_number.png';
    echo 'Generating QR number for ' . $attendee->id . ' ' . $barcode . PHP_EOL;
    \PHPQRCode\QRcode::png($barcode, $numberFilename, 'L', 30, 4);

    $vcardFilename = __DIR__ . '/../qr/' . $attendee->id . '_vcard.png';
    echo 'Generating QR vcard for ' . $attendee->id . ' ' . $barcode . PHP_EOL;
    \PHPQRCode\QRcode::png($vcard->buildVCard(), $vcardFilename, 'L', 30, 4);
}

ksort($data);

foreach ($data as $person) {
    $row = array_map(function($value) {
        return '"' . $value . '"';
    }, $person);
    echo implode(',', $row);
    echo PHP_EOL;
}
