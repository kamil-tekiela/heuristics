<?php

use Reports\Reports;

define('BASE_DIR', realpath(__DIR__.'/..'));

define('PERPAGE', 100);
define('REPORT_URL', 'https://bot.dharman.net/reports.php');

include BASE_DIR.'/vendor/autoload.php';

$dotEnv = new DotEnv();
$dotEnv->load(BASE_DIR.'/config.ini');

$db = \ParagonIE\EasyDB\Factory::fromArray([
	'sqlite:'.BASE_DIR.'/data/db.db'
]);

$controller = new Reports($db);

$page = $_GET['page'] ?? 1;
$searchType = $_GET['type'] ?? '';
$searchValue = $_GET['value'] ?? '';

$reports = [];
if ($searchType || $searchValue) {
	$reports = $controller->fetchBySearch($page, $searchType, $searchValue);
}

$report_count = $controller->getCount();

$reasons = $controller->fetchReasons(array_column($reports, 'Id'));

$maxPage = ceil(($report_count ?? 0) / PERPAGE);

$title = 'Reports - search';

include 'views/header.phtml';
include 'views/search.phtml';
include 'views/searchResults.phtml';
include 'views/footer.phtml';
