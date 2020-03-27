<?php

/**
 * Config class - stores all environment variables loaded from config file
 */
class DotEnv
{
	private static $config = [];

	/**
	 * Load the config file
	 *
	 * @param string $path
	 * @return void
	 */
	public static function load(string $path)
	{
		// open and parse the config file
		$config = \parse_ini_file($path, true);
		if (!$config) {
			throw new \Exception('No config file!');
		}
		foreach ($config as $key => $value) {
			if (\is_array($value)) {
				foreach ($value as $key2 => $value2) {
					self::$config[\strtolower($key.'.'.$key2)] = $value2;
				}
			} else {
				self::$config[\strtolower($key)] = $value;
			}
		}
	}

	/**
	 * Getter for config. Returns the configuration variable
	 *
	 * @param string $key
	 * @return string|null
	 */
	public static function get(string $key): ?string
	{
		return self::$config[\strtolower($key)] ?? null;
	}
}
