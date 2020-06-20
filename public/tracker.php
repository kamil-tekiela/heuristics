<?php

$searchString = $argv[2] ?? null;
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

$failedTries = 0;
while (1) {
	try {
		$fetcher->fetch();
	} catch (\Throwable $e) {
		$failedTries++;
		file_put_contents(BASE_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.date('Y_m_d_H_i_s').'.log', $e->getMessage().PHP_EOL.print_r($e->getTrace(), true));
		sleep($failedTries * 60);
		// keep trying up to n times
		if ($failedTries >= 10) {
			throw $e;
		}
		continue;
	}

	$failedTries = 0;

	sleep(75);
}
