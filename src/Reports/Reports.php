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

	private $rowCount = 0;

	public function fetch(int $page = 1, int $minScore = 0, ?int $maxScore = null) {
		$offset = PERPAGE * ($page - 1);

		if ($maxScore) {
			/** @var array[] */
			$data = $this->db->safeQuery('SELECT reports.*, EXISTS(SELECT answer_id FROM flags WHERE report_id = reports.Id) AS flagged
			FROM reports 
			WHERE score >= ? AND score <= ?
			ORDER BY id DESC
			LIMIT 600 OFFSET ?', [$minScore, $maxScore, $offset]);
		} else {
			/** @var array[] */
			$data = $this->db->safeQuery('SELECT reports.*, EXISTS(SELECT answer_id FROM flags WHERE report_id = reports.Id) AS flagged
			FROM reports 
			WHERE score >= ?
			ORDER BY id DESC
			LIMIT 600 OFFSET ?', [$minScore, $offset]);
		}

		$this->rowCount = $offset + count($data);

		return array_slice($data, 0, PERPAGE);
	}

	public function getCount() {
		return $this->rowCount;
	}

	public function fetchByIds(array $id) {
		$statement = EasyStatement::open()->in('Id IN (?*)', $id);

		/** @var array[] */
		return $this->db->safeQuery('SELECT reports.*, EXISTS(SELECT answer_id FROM flags WHERE report_id = reports.Id) AS flagged
			FROM reports 
			WHERE '.$statement.' 
			ORDER BY id DESC', $statement->values());
	}

	public function fetchReasons(array $reports) {
		if (!$reports) {
			return [];
		}

		$statement = EasyStatement::open()->in('report_id IN (?*)', $reports);

		/** @var array<int, array[]> */
		return $this->db->safeQuery('SELECT report_id, type, value, weight
			FROM reasons 
			WHERE '.$statement, $statement->values(), PDO::FETCH_GROUP);
	}

	public function fetchBySearch(int $page = 1, string $type, string $value) {
		$offset = PERPAGE * ($page - 1);

		$statement = EasyStatement::open();
		if ($value) {
			$statement->andWith('reasons.value LIKE ?', '%' . $value . '%');
		}
		if ($type) {
			$statement->andWith('reasons.type LIKE ?', '%' . $this->db->escapeLikeValue($type) . '%');
		}
		$values = array_merge($statement->values(), [$offset]);

		/** @var array[] */
		$data = $this->db->safeQuery("SELECT reports.*, EXISTS(SELECT answer_id FROM flags WHERE report_id = reports.Id) AS flagged 
			FROM reports 
			INNER JOIN reasons ON reasons.report_id=reports.Id
			WHERE {$statement} 
			GROUP BY report_id
			ORDER BY reports.Id DESC
			LIMIT 600 OFFSET ?", $values);

		$this->rowCount = $offset + count($data);

		return array_slice($data, 0, PERPAGE);
	}
}
