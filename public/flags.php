<?php

use Flags\Flags;

define('BASE_DIR', realpath(__DIR__.'/..'));

define('PERPAGE', 100);
define('REPORT_URL', 'https://bot.dharman.net/reports.php');

include BASE_DIR.'/vendor/autoload.php';

$dotEnv = new DotEnv();
$dotEnv->load(BASE_DIR.'/config.ini');

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/data/db.db'
]);

$controller = new Flags($db);

if (isset($_GET['getCountJson'])) {
	header('Content-Type: application/json; charset=utf-8');
	die(json_encode($controller->getCountByDay(
		new DateTimeImmutable($_GET['startDay'] ?? '-1 month'),
		new DateTimeImmutable($_GET['endDay'] ?? 'now'),
	)));
}

$page = $_GET['page'] ?? 1;

$flags = $controller->fetch($page);
$flag_count = $controller->getCount();

$maxPage = ceil(($flag_count ?? 0) / PERPAGE);

// Day subtotal
$flagsByDay = [];
$allDays = [];
foreach ($flags as $flag) {
	$date = new DateTimeImmutable($flag['created_at']);
	$allDays[] = $date;
	$flagsByDay[$date->format('Y-m-d')][] = $flag;
}
if ($allDays === []) {
	$allDays[] = new DateTime();
}
$flagCountByDay = $controller->getCountByDay(min($allDays), max($allDays));

$title = 'Flagged posts';

include BASE_DIR.'/views/header.phtml';
include BASE_DIR.'/views/flags.phtml';
include BASE_DIR.'/views/footer.phtml';
