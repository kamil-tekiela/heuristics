<?php

define('DEBUG', 0);
define('BASE_DIR', realpath(__DIR__.'/..'));

include BASE_DIR.'/vendor/autoload.php';

$dotEnv = new DotEnv();
$dotEnv->load(BASE_DIR.'/config.ini');

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/db.db'
]);

$client = new GuzzleHttp\Client();

// DB_setup::setup($db);

$chatAPI = new ChatAPI($dotEnv);

$stackAPI = new StackAPI($client);

$fetcher = new AnswerAPI($db, $client, $stackAPI, $chatAPI, $dotEnv);

while (1) {
	$fetcher->fetch();
	sleep(60);
}
