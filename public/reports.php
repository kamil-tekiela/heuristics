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

if (isset($_GET['id'])) {
	$reports = $controller->fetchByIds(explode(';',$_GET['id']));
} else {
	$reports = $controller->fetch($_GET['minScore'] ?? 4, $page-1);
	$report_count = $controller->getCount($_GET['minScore'] ?? 4);
}
$reasons = $controller->fetchReasons(array_column($reports, 'Id'));


$maxPage = ceil(($report_count ?? 0) / 100);

//HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Reports</title>
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
	<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
<style>
.reasons{
	font-size: 0.8rem;
}
</style>
</head>
<body>
<?php

foreach ($reports as $report) {
	?>
	<div class="container  mb-5 border">
		<h2><a href="https://stackoverflow.com/a/<?=$report['answer_id']?>"><?=$report['answer_id']?></a></h2>
		<div class="reported_at"><b>Date:</b> <?=$report['reported_at']?></div>
		<div class="score">
			<b>Score:</b> <span class="badge badge-primary"><?=$report['score']?></span>
		</div>
		<div class="score">
			<b>Natty:</b> <span class="natty_score badge badge-secondary"><?=$report['natty_score']?></span>
		</div>
		<div class="summary"><a href="<?=REPORT_URL.'?id='.$report['Id']?>"><b>Report link</b></a></div>
		<div class="body border shadow-sm p-3 mb-3 bg-white rounded"><?=$report['body']?></div>
		<?php
		if (isset($reasons[$report['Id']])) {
			echo 'Reasons:';
			echo '<ul class="reasons">';
			foreach ($reasons[$report['Id']] as $reason) {
				echo '<li>';
				echo "<b>{$reason['type']} ({$reason['weight']}):</b> {$reason['value']}";
				echo '</li>';
			}
			echo '</ul>';
		} ?>
	</div>
	<?php
}

?>
	<div class="container ">
		<nav aria-label="Page navigation example" class="d-flex justify-content-center">
		<ul class="pagination">
		<?php if($page>1): ?>
			<li class="page-item"><a class="page-link" href="?<?=http_build_query(array_merge($_GET, ['page'=>$page-1])); ?>">Previous</a></li>
		<?php endif; 

		for($i=max(1, $page-5); $i<min($maxPage, $page+5); $i++){
			echo '<li class="page-item"><a class="page-link" href="?'.http_build_query(array_merge($_GET, ['page'=>$i])).'">'.$i.'</a></li>';
		}

		if($page<$maxPage): ?>
			<li class="page-item"><a class="page-link" href="?<?=http_build_query(array_merge($_GET, ['page'=>$page+1])); ?>">Next</a></li>
		<?php endif; ?>
		</ul>
		</nav>
	</div>
</body>
</html>