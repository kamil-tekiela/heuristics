<?php

define('DEBUG', 0);
define('BASE_DIR', realpath(__DIR__.'/..'));

include BASE_DIR.'/vendor/autoload.php';

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/db.db'
]);
$client = new GuzzleHttp\Client();

DB_setup::setup($db);

$chatAPI = new ChatAPI(210133);

$fetcher = new APIFetcher($db, $client, $chatAPI);

while (1) {
	$fetcher->fetch();
	sleep(45);
}
