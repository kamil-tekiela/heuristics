<?php

define('DEBUG', 0);
define('BASE_DIR', realpath(__DIR__.'/..'));

include BASE_DIR.'/vendor/autoload.php';

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/db.db'
]);
$client = new GuzzleHttp\Client();

DB_setup::setup($db);

$fetcher = new CommentAPI($db, $client, '1 year ago');

while (1) {
	$fetcher->fetch();
	if ($fetcher->running_count >= 50) {
		break;
	}
	sleep(30);
}
