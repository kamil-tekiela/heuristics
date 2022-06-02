<?php

declare(strict_types=1);

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

$page = intval($_GET['page'] ?? 1);

if (isset($_GET['id'])) {
	$reports = $controller->fetchByIds(explode(';', $_GET['id']));
} else {
	$reports = $controller->fetch($page, floatval($_GET['minScore'] ?? 4), floatval($_GET['maxScore'] ?? null));
	$report_count = $controller->getCount();
}
$reasons = $controller->fetchReasons(array_column($reports, 'Id'));

$maxPage = ceil(($report_count ?? 0) / PERPAGE);

$title = 'Reports';

include BASE_DIR.'/views/header.phtml';
include BASE_DIR.'/views/reports.phtml';
include BASE_DIR.'/views/footer.phtml';
