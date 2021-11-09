<?php

declare(strict_types=1);

namespace Flags;

use ParagonIE\EasyDB\EasyDB;

class Flags {
	private EasyDB $db;

	public function __construct(EasyDB $db) {
		$this->db = $db;
	}

	private $rowCount = 0;

	public function fetch(int $page = 1) {
		$offset = PERPAGE * ($page - 1);

		$data = $this->db->safeQuery('SELECT *
			FROM flags 
			ORDER BY ROWID DESC
			LIMIT 600 OFFSET ?', [$offset]);

		$this->rowCount = $offset + count($data);

		return array_slice($data, 0, PERPAGE);
	}

	public function getCount() {
		return $this->rowCount;
	}
}
