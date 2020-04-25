<?php

define('VERSION', '2.0');
define('BASE_DIR', realpath(__DIR__.'/..'));
define('REPORT_URL', 'https://bot.dharman.net/reports.php');

include BASE_DIR.'/vendor/autoload.php';

$dotEnv = new DotEnv();
$dotEnv->load(BASE_DIR.'/config.ini');
define('DEBUG', (bool) $dotEnv->get('DEBUG'));

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/data/db.db'
]);

$client = new GuzzleHttp\Client();

// DB_setup::setup($db);

$chatAPI = new ChatAPI($dotEnv);

$stackAPI = new StackAPI($client);

$fetcher = new AnswerAPI($db, $client, $stackAPI, $chatAPI, $dotEnv);

while (1) {
	$fetcher->fetch();
	if (DEBUG) {
		break;
	}
	sleep(60);
}
