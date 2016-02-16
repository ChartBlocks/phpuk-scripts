<?php

chdir(__DIR__ . '/../');

require('vendor/autoload.php');
$config = require('config/local.php');

// The OAuth credentials you received when registering your app at Twitter
define("TWITTER_CONSUMER_KEY", $config['twitter']['key']);
define("TWITTER_CONSUMER_SECRET", $config['twitter']['secret']);

// The OAuth data for the twitter account
define("OAUTH_TOKEN", $config['twitter']['token']);
define("OAUTH_SECRET", $config['twitter']['token_secret']);

$rangeStart = strtotime('2016-02-16 18:00');
$rangeEnd = strtotime('2016-02-20 23:00');
$rangeStep = 3600;
$ranges = array_fill_keys(range($rangeStart, $rangeEnd, $rangeStep), null);

echo 'Processing ' . count($ranges) . ' ranges' . PHP_EOL;
$mongo = new \MongoClient();
$collection = $mongo->selectCollection($config['mongo']['database'], $config['mongo']['collection']);


$cb = new ChartBlocks\Client(array(
    'token' => $config['chartblocks']['token'],
    'secret' => $config['chartblocks']['secret'],
        ));

$set = $cb->getRepository('dataSet')->findById($config['chartblocks']['tweetSetId']);
$counts = array();

foreach ($ranges as $start => $count) {
    $getCount = function($track, $collection, $start, $step) {
        $count = $collection->count(array(
            'created_at' => array(
                '$gte' => new \MongoDate($start),
                '$lt' => new \MongoDate($start + $step)
            ),
            'entities.hashtags.text' => array('$in' => $track),
        ));

        if ($count == 0 && $start > time()) {
            $count = null;
        }

        return $count;
    };

    $phpCount = $getCount($config['twitter']['tracks']['php'], $collection, $start, $rangeStep);

    $counts[date('c', $start)] = array(
        'php' => $phpCount,
    );
}

$r = 2;
$rowSet = new \ChartBlocks\DataSet\RowSetDynamic($set);
foreach ($counts as $date => $count) {
    $row = new \ChartBlocks\DataSet\Row($set, array('row' => $r));
    $row->setCell(1, $date);
    $row->setCell(2, $count['php']);

    $rowSet->addRow($row);
    $r++;
}

$rowSet->save();
