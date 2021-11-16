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

$chartData = $controller->getMonthCount();

if (isset($_GET['getCountJson'])) {
	die(json_encode($chartData));
}

$page = $_GET['page'] ?? 1;

$flags = $controller->fetch($page);
$flag_count = $controller->getCount();

$maxPage = ceil(($flag_count ?? 0) / PERPAGE);

// Day subtotal
$flagsByDay = [];
foreach ($flags as $flag) {
	$flagsByDay[(new DateTime($flag['created_at']))->format('Y-m-d')][] = $flag;
}

$title = 'Flagged posts';

include BASE_DIR.'/views/header.phtml';
include BASE_DIR.'/views/flags.phtml';
include BASE_DIR.'/views/footer.phtml';