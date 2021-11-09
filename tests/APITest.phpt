<?php

ob_start();

use Dharman\ChatAPI;
use Dharman\StackAPI;

define('VERSION', '3.6');
define('BASE_DIR', realpath(__DIR__.'/..'));
define('REPORT_URL', 'https://bot.dharman.net/reports.php');

include BASE_DIR.'/vendor/autoload.php';

$dotEnv = new DotEnv();
$dotEnv->load(BASE_DIR.'/config.ini');
define('DEBUG', "68581965;14753190");
define('DEBUG_OLD', NULL);

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/data/db.db'
]);

$client = new GuzzleHttp\Client();

// DB_setup::setup($db);

$chatAPI = new ChatAPI($dotEnv->get('chatUserEmail'), $dotEnv->get('chatUserPassword'), BASE_DIR.'/data/chatAPI_cookies.json');

$stackAPI = new StackAPI($client);

$fetcher = new AnswerAPI($db, $client, $stackAPI, $chatAPI, $dotEnv);

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

	if (DEBUG) {
		break;
	}

	sleep(60);
}

$output = ob_get_clean();

assert(
	1 === preg_match('/(*ANYCRLF)
	^\d{4}-\d{2}-\d{2}\h\d{2}:\d{2}:\d{2}\sto\h\d{4}-\d{2}-\d{2}\h\d{2}:\d{2}:\d{2}(\r|\n)+
	Processing\hfinished\hat:\s\d{4}-\d{2}-\d{2}\h\d{2}:\d{2}:\d{2}(\r|\n)+
	$/x', $output)
);

echo 'PASSED';
