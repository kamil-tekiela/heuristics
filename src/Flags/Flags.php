<?php

declare(strict_types=1);

namespace Flags;

use DateTimeInterface;
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

	public function getCountByDay(DateTimeInterface $startDay, DateTimeInterface $endDay): array {
		/** @var array<string, int> $data */
		$data = $this->db->safeQuery('SELECT date(created_at), COUNT(*) 
            FROM flags
			WHERE date(created_at) >= ? AND date(created_at) <= ?
			GROUP BY date(created_at)
            ORDER BY date(created_at)', [$startDay->format('Y-m-d'), $endDay->format('Y-m-d')], \PDO::FETCH_KEY_PAIR);

		return $data;
	}
}
