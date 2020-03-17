<?php

class Salutations {
	private static $words = [];

	static function getPhrases(): array {
		if (!self::$words) {
			$words = json_decode(file_get_contents(__DIR__.'/../data/Salutations.json'));
			foreach ($words->items as $phrase) {
				self::$words[] = $phrase->name;
			}
		}
		return self::$words;
	}
}