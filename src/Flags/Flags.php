<?php

declare(strict_types=1);

namespace Flags;

use ParagonIE\EasyDB\EasyDB;

class Flags {
	private EasyDB $db;

	public function __construct(EasyDB $db) {
		$this->db = $db;
	}

	private int $rowCount = 0;

	public function fetch(int $page = 1): array {
		$offset = PERPAGE * ($page - 1);

		/** @var array[] */
		$data = $this->db->safeQuery('SELECT *
			FROM flags 
			ORDER BY ROWID DESC
			LIMIT 600 OFFSET ?', [$offset]);

		$this->rowCount = $offset + count($data);

		return array_slice($data, 0, PERPAGE);
	}

	public function getCount(): int {
		return $this->rowCount;
	}

	public function getMonthCount(): array {
		/** @var array<string, int> $data */
		$data = $this->db->safeQuery('SELECT date(created_at), COUNT(*) 
            FROM flags
			WHERE created_at > date("now", "-1 month")
			GROUP BY date(created_at)
            ORDER BY date(created_at)', [], \PDO::FETCH_KEY_PAIR);

		return $data;
	}
}