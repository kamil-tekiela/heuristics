<?php

namespace Reports;

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\EasyStatement;
use PDO;

class Reports {
	/**
	 * Connection to DB
	 *
	 * @var EasyDB
	 */
	private $db;

	public function __construct(EasyDB $db) {
		$this->db = $db;
	}

	public function fetch(int $minScore=0, int $page = 0) {
		$offset = 100 * $page;
		return $this->db->safeQuery('SELECT * 
			FROM reports 
			WHERE score >= ?
			ORDER BY reported_At DESC
			LIMIT 100 OFFSET ?', [$minScore, $offset]);
	}

	public function fetchByIds(array $id) {
		$statement = EasyStatement::open()->in('Id IN (?*)', $id);

		return $this->db->safeQuery('SELECT * 
			FROM reports 
			WHERE '.$statement, $statement->values());
	}

	public function fetchReasons(array $reports)
	{
		$statement = EasyStatement::open()->in('report_id IN (?*)', $reports);
		
		return $this->db->safeQuery('SELECT reasons.* 
			FROM reasons 
			WHERE '.$statement, $statement->values(), PDO::FETCH_GROUP);
	}
}
