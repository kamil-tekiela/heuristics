<?php

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client as Guzzle;

class StackAPI {
	/**
	 * Guzzle
	 *
	 * @var Guzzle
	 */
	private $client;

	/**
	 * My app key. Not secret
	 */
	private const APP_KEY = 'gS)WzUg0j7Q5ZVEBB5Onkw((';

	/**
	 * My app key. Not secret
	 */
	private const TIMEFILE = 'nextRqPossibleAt';

	/**
	 * Time the next request can be made at.
	 *
	 * @var float
	 */
	private $nextRqPossibleAt = 0.0;

	public function __construct(Guzzle $client) {
		$this->client = $client;
		if (file_exists(self::TIMEFILE)) {
			$this->nextRqPossibleAt = (float) file_get_contents(self::TIMEFILE);
		}
	}

	public function request(string $method, string $url, array $args): stdClass {
		// handle backoff properly
		$timeNow = microtime(true);
		if ($timeNow < $this->nextRqPossibleAt) {
			$backoffTime = ceil($this->nextRqPossibleAt - $timeNow);
			echo 'Backing off for '.$backoffTime.' seconds'.PHP_EOL;
			sleep($backoffTime);
		}

		// enhance with API key for more quota
		$args += [
			'key' => self::APP_KEY
		];

		try {
			if ($method == 'GET') {
				$rq = $this->client->request($method, $url, ['query' => $args]);
			} else {
				$rq = $this->client->request($method, $url, ['form_params' => $args]);
			}
		} catch (RequestException $e) {
			throw new Exception(Psr7\str($e->getResponse()));
		}
		
		$contents = json_decode($rq->getBody()->getContents());
		
		$this->nextRqPossibleAt = microtime(true);
		if (isset($contents->backoff)) {
			echo 'I was told to back off for '.$contents->backoff.' seconds'.PHP_EOL;
			$this->nextRqPossibleAt + $contents->backoff;
		}
		file_put_contents(self::TIMEFILE, $this->nextRqPossibleAt);

		return $contents;
	}
}
