<?php

$searchString = $argv[2] ?: null;
define('DEBUG', isset($argv[1]) && $argv[1] == 1);
define('BASE_DIR', realpath(__DIR__.'/..'));

include BASE_DIR.'/vendor/autoload.php';

$dotEnv = new DotEnv();
$dotEnv->load(BASE_DIR.'/config.ini');

$client = new GuzzleHttp\Client();

$chatAPI = new ChatAPI($dotEnv);

$stackAPI = new StackAPI($client);

$fetcher = new Tracker\TrackerAPI($client, $stackAPI, $chatAPI, $dotEnv);

if ($searchString) {
	$fetcher->fetch($searchString);
	exit;
}

while (1) {
	$fetcher->fetch();
	sleep(75);
}
