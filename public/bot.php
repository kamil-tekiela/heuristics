<?php

use Dharman\ChatAPI;
use Dharman\StackAPI;

define('VERSION', '3.8');
define('BASE_DIR', realpath(__DIR__.'/..'));
define('REPORT_URL', 'https://bot.dharman.net/reports.php');

include BASE_DIR.'/vendor/autoload.php';

$dotEnv = new DotEnv();
$dotEnv->load(BASE_DIR.'/config.ini');
define('DEBUG', $dotEnv->get('DEBUG'));
define('DEBUG_OLD', $dotEnv->get('DEBUG_OLD'));

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/data/db.db'
]);

$client = new GuzzleHttp\Client();

$chatAPI = new ChatAPI($dotEnv->get('chatUserEmail'), $dotEnv->get('chatUserPassword'), BASE_DIR.'/data/chatAPI_cookies.json');

$stackAPI = new StackAPI($client);

$fetcher = new AnswerAPI($db, $client, $stackAPI, $chatAPI, $dotEnv);

$failedTries = 0;
while (true) {
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

	if (DEBUG) {
		break;
	}

	// hot reload some settings
	$dotEnv->load(BASE_DIR.'/config.ini');

	sleep(60);
}
