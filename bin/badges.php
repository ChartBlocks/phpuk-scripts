<?php

chdir(__DIR__ . '/../');

require('vendor/autoload.php');
$config = require('config/local.php');

$authentication_tokens = array(
    'app_key' => $config['eventbrite']['appKey'],
    'user_key' => $config['eventbrite']['userKey']
);

$verbose = in_array('-v', $argv);
$eb = new Php2014\EventBrite($authentication_tokens);

$event = $eb->event_get(array('id' => $config['eventbrite']['eventId']));
$tickets = array();

if ($verbose)
    echo sprintf('Found %d ticket types', count($event->event->tickets)) . PHP_EOL;

foreach ($event->event->tickets as $ticketObject) {
    $ticket = $ticketObject->ticket;
    $tickets[$ticket->id] = $ticket;

    if ($verbose)
        echo sprintf('%s  %s', $ticket->id, $ticket->name) . PHP_EOL;
}

if ($verbose)
    echo PHP_EOL;

$result = $eb->event_list_attendees(array(
    'id' => $config['eventbrite']['eventId'],
    'status' => 'attending',
    'show_full_barcodes' => 'true',
//    'count' => 10
        ));

$data = array();

if ($verbose)
    echo sprintf('Found %d attendees', count($result->attendees)) . PHP_EOL;

$ticketTypeMap = $config['eventbrite']['types'];

foreach ($result->attendees as $object) {
    $attendee = $object->attendee;

    $barcode = null;
    foreach ($attendee->barcodes as $barcodeObject) {
        $barcode = $barcodeObject->barcode->id;
    }

    $ticketId = $attendee->ticket_id;
    $ticketName = $tickets[$ticketId]->name;
    $ticketPeriod = $tickets[$ticketId]->name;

    if (array_key_exists($ticketId, $ticketTypeMap)) {
        $ticketName = $ticketTypeMap[$ticketId][0];
        $ticketPeriod = $ticketTypeMap[$ticketId][1];
    } elseif ($verbose) {
        echo sprintf('Missing type map for %s %s', $ticketId, $ticketName) . PHP_EOL;
    }

    $data[$attendee->id] = array(
        'id' => $attendee->id,
        'name' => mb_convert_case($attendee->first_name . ' ' . $attendee->last_name, MB_CASE_TITLE, "UTF-8"),
        'company' => $attendee->company,
        'ticket' => $ticketName,
        'period' => $ticketPeriod,
        'barcode' => $barcode,
        'qre' => $attendee->id . '_number.png',
        'vcard' => $attendee->id . '_vcard.png',
    );
}

ksort($data);

foreach ($data as $person) {
    $row = array_map(function($value) {
        return '"' . $value . '"';
    }, $person);
    echo implode(',', $row);
    echo PHP_EOL;
}