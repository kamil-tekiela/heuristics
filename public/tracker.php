<?php

if (isset($argv[1])) {
	define('DEBUG', 1);
} else {
	define('DEBUG', 0);
}
define('BASE_DIR', realpath(__DIR__.'/..'));

include BASE_DIR.'/vendor/autoload.php';

$dotEnv = new DotEnv();
$dotEnv->load(BASE_DIR.'/config.ini');

$client = new GuzzleHttp\Client();

$chatAPI = new ChatAPI($dotEnv);

$stackAPI = new StackAPI($client);

$fetcher = new Tracker\TrackerAPI($client, $stackAPI, $chatAPI, $dotEnv);

while (1) {
	$fetcher->fetch();
	sleep(75);
}
