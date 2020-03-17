<?php

use ParagonIE\EasyDB\EasyDB;

class DB_setup {
	public static function setup(EasyDB $db) {
		// $db->run('DROP TABLE IF EXISTS blacklist');
		if (!self::tableExists($db, 'blacklist')) {
			$db->run('CREATE TABLE IF NOT EXISTS blacklist(
				Word,
				Type,
				Weight
			)');

			$db->insertMany('blacklist', array_map(function ($el) {
				return ['Word' => $el, 'Type' => 'salutation'];
			}, Salutations::getPhrases()));

			$db->insertMany('blacklist', (function () {
				$list = file_get_contents(__DIR__.'/../data/blacklist.json');
				$json = json_decode($list)->items;
				$list = [];
				foreach ($json as $item) {
					$list[] = ['Word' => $item->name, 'Type' => 'blacklist'];
				}
				return $list;
			})());
		}
	}

	public static function tableExists(EasyDB $db, string $table) {
		return $db->exists("SELECT name FROM sqlite_master WHERE type='table' AND name=?", $table);
	}
}
