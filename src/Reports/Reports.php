<?php

namespace Reports;

use ParagonIE\EasyDB\EasyDB;
use ParagonIE\EasyDB\EasyStatement;
use PDO;

class Reports {
	private EasyDB $db;

	public function __construct(EasyDB $db) {
		$this->db = $db;
	}

	private int $rowCount = 0;

	public function fetch(int $page = 1, float $minScore = 0, ?float $maxScore = null): array {
		$offset = PERPAGE * ($page - 1);

		if ($maxScore) {
			/** @var array[] */
			$data = $this->db->safeQuery('SELECT reports.*, flags.answer_id AS flagged
			FROM reports 
			LEFT JOIN flags ON flags.report_id = reports.Id
			WHERE score >= ? AND score <= ?
			ORDER BY id DESC
			LIMIT 600 OFFSET ?', [$minScore, $maxScore, $offset]);
		} else {
			/** @var array[] */
			$data = $this->db->safeQuery('SELECT reports.*, flags.answer_id AS flagged
			FROM reports 
			LEFT JOIN flags ON flags.report_id = reports.Id
			WHERE score >= ?
			ORDER BY id DESC
			LIMIT 600 OFFSET ?', [$minScore, $offset]);
		}

		$this->rowCount = $offset + count($data);

		return array_slice($data, 0, PERPAGE);
	}

	public function getCount(): int {
		return $this->rowCount;
	}

	public function fetchByIds(array $id): array {
		$statement = EasyStatement::open()->in('Id IN (?*)', $id);

		/** @var array[] */
		return $this->db->safeQuery('SELECT reports.*, flags.answer_id AS flagged
			FROM reports 
			LEFT JOIN flags ON flags.report_id = reports.Id
			WHERE '.$statement.' 
			ORDER BY id DESC', $statement->values());
	}

	public function fetchReasons(array $reports): array {
		if (!$reports) {
			return [];
		}

		$statement = EasyStatement::open()->in('report_id IN (?*)', $reports);

		/** @var array<int, array[]> */
		return $this->db->safeQuery('SELECT report_id, type, value, weight
			FROM reasons 
			WHERE '.$statement, $statement->values(), PDO::FETCH_GROUP);
	}

	public function fetchBySearch(int $page = 1, string $type, string $value): array {
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
		$data = $this->db->safeQuery("SELECT reports.*, flags.answer_id AS flagged
			FROM reports 
			INNER JOIN reasons ON reasons.report_id=reports.Id
			LEFT JOIN flags ON flags.report_id = reports.Id
			WHERE {$statement} 
			GROUP BY reasons.report_id
			ORDER BY reasons.report_id DESC
			LIMIT 600 OFFSET ?", $values);

		$this->rowCount = $offset + count($data);

		return array_slice($data, 0, PERPAGE);
	}
}
