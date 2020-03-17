<?php

define('DEBUG', 0);
define('BASE_DIR', realpath(__DIR__.'/..'));

include BASE_DIR.'/vendor/autoload.php';

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/db.db'
]);
$client = new GuzzleHttp\Client();

DB_setup::setup($db);

$fetcher = new APIFetcher($db, $client);

while (1) {
	$fetcher->fetch();
	sleep(45);
}
