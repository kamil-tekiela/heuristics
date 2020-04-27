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

	public function fetch(int $minScore = 0, int $page = 1) {
		$offset = PERPAGE * ($page - 1);

		$this->rowCount = $this->db->single('SELECT COUNT(*)
			FROM reports 
			WHERE score >= ?', [$minScore]);

		return $this->db->safeQuery('SELECT *
			FROM reports 
			WHERE score >= ?
			ORDER BY reported_At DESC
			LIMIT 100 OFFSET ?', [$minScore, $offset]);
	}

	public function getCount() {
		return $this->rowCount;
	}

	public function fetchByIds(array $id) {
		$statement = EasyStatement::open()->in('Id IN (?*)', $id);

		return $this->db->safeQuery('SELECT * 
			FROM reports 
			WHERE '.$statement.' 
			ORDER BY reported_At DESC', $statement->values());
	}

	public function fetchReasons(array $reports) {
		if (!$reports) {
			return [];
		}

		$statement = EasyStatement::open()->in('report_id IN (?*)', $reports);
		
		return $this->db->safeQuery('SELECT reasons.* 
			FROM reasons 
			WHERE '.$statement, $statement->values(), PDO::FETCH_GROUP);
	}

	public function fetchBySearch(int $page = 1, string $type, string $value) {
		$statement = EasyStatement::open();
		if ($value) {
			$statement->andWith('value LIKE ?', '%' . $value . '%');
		}
		if ($type) {
			$statement->andWith('type LIKE ?', '%' . $this->db->escapeLikeValue($type) . '%');
		}
		$values = array_merge($statement->values(), [PERPAGE * ($page - 1)]);

		$this->rowCount = $this->db->single('SELECT COUNT(DISTINCT report_id)
			FROM reports 
			LEFT JOIN reasons ON reasons.report_id=reports.Id
			WHERE '.$statement.' 
			 ', $statement->values());

		return $this->db->safeQuery('SELECT * 
			FROM reports 
			LEFT JOIN reasons ON reasons.report_id=reports.Id
			WHERE '.$statement.' 
			GROUP BY report_id
			ORDER BY reported_At DESC
			LIMIT 100 OFFSET ?', $values);
	}

	public function getAvgScore(string $type, string $value) {
		$statement = EasyStatement::open();
		if ($value) {
			$statement->andWith('value LIKE ?', '%' . $value . '%');
		}
		if ($type) {
			$statement->andWith('type LIKE ?', '%' . $this->db->escapeLikeValue($type) . '%');
		}

		return $this->db->single('SELECT AVG(score) 
			FROM reports 
			WHERE Id IN (
				SELECT report_id FROM reasons
				WHERE '.$statement.' 
				GROUP BY report_id
			)', $statement->values());
	}
}
