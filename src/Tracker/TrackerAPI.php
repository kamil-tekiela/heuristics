<?php

declare(strict_types=1);

namespace Tracker;

use ParagonIE\EasyDB\EasyDB;

class TrackerAPI {
	/**
	 * Guzzle
	 *
	 * @var \GuzzleHttp\Client
	 */
	private $client;

	/**
	 * Timestamp
	 *
	 * @var int
	 */
	private $lastRequestTime;

	/**
	 * Token for Dharman user. Secret!
	 *
	 * @var string
	 */
	private $userToken = '';

	/**
	 * Chat API to talk in the chat
	 *
	 * @var ChatAPI
	 */
	private $chatAPI = null;

	/**
	 * Stack API class for using the official Stack Exchange API
	 *
	 * @var StackAPI
	 */
	private $stackAPI = null;

	private $logRoomId = null;

	public function __construct(\GuzzleHttp\Client $client, \StackAPI $stackAPI, \ChatAPI $chatAPI, \DotEnv $dotEnv) {
		$this->client = $client;
		$this->chatAPI = $chatAPI;
		$this->stackAPI = $stackAPI;
		// $this->lastRequestTime = $this->db->single('SELECT `time` FROM lastRequest');
		if (!$this->lastRequestTime) {
			$this->lastRequestTime = strtotime('15 minutes ago');
		}

		$this->userToken = $dotEnv->get('key');
		if (!$this->userToken) {
			throw new \Exception('Please login first and provide valid user token!');
		}
		$this->logRoomId = (int) $dotEnv->get('trackRoomId');
		if (!$this->logRoomId) {
			throw new \Exception('Please provide valid room ID!');
		}

		// Say hello
		$this->chatAPI->sendMessage($this->logRoomId, 'TrackerBot started on '.gethostname());
	}

	/**
	 * Entry point. Fetches a bunch of answers and their questions and then parses them.
	 *
	 * @return void
	 */
	public function fetch() {
		$apiEndpoint = 'questions';
		$url = "https://api.stackexchange.com/2.2/" . $apiEndpoint;
		if (DEBUG) {
			$url .= '/60765207';
		}
		$args = [
			'todate' => strtotime('2 minutes ago'),
			'site' => 'stackoverflow',
			'order' => 'asc',
			'sort' => 'creation',
			'filter' => '7yrx3gca'
		];
		$args['fromdate'] = $this->lastRequestTime + 1;

		echo(date_create_from_format('U', (string) $args['fromdate'])->format('Y-m-d H:i:s')). ' to '.(date_create_from_format('U', (string) $args['todate'])->format('Y-m-d H:i:s')).PHP_EOL;

		// Request questions
		try {
			$contents = $this->stackAPI->request('GET', $url, $args);
		} catch (\Exception $e) {
			file_put_contents(BASE_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.date('Y_m_d_H_i_s').'.log', $e->getMessage());
		}

		foreach ($contents->items as $postJSON) {
			$post = new Question($postJSON);

			// Our rules
			$line = '';
			if (stripos($post->bodyWithTitle, 'mysqli') !== false) {
				$line = "[tag:mysqli] [{$post->title}]({$post->link})".PHP_EOL;
			} elseif (preg_match('#mysql_(?:query|connect|select_db|error|fetch|num_rows|escape_string|close|result)#i', $post->bodyWithTitle)) {
				$line = "[tag:mysql_*] [{$post->title}]({$post->link})".PHP_EOL;
			} elseif (preg_match('#fetch_(?:assoc|array|row|object|num|both|all|field)#i', $post->bodyWithTitle)) {
				$line = "[tag:mysqli] [{$post->title}]({$post->link})".PHP_EOL;
			} elseif (stripos($post->bodyWithTitle, '->query') !== false) {
				$line = "[tag:mysqli] [{$post->title}]({$post->link})".PHP_EOL;
			} elseif (stripos($post->bodyWithTitle, 'bind_param') !== false) {
				$line = "[tag:mysqli] [{$post->title}]({$post->link})".PHP_EOL;
			} elseif (stripos($post->bodyWithTitle, '->error') !== false) {
				$line = "[tag:mysqli] [{$post->title}]({$post->link})".PHP_EOL;
			} elseif (in_array('mysqli', $post->tags, true)) {
				$line = "[tag:mysqli] [{$post->title}]({$post->link})".PHP_EOL;
			}

			if ($line) {
				$tags = array_reduce($post->tags, function ($carry, $e) {
					return $carry."[tag:{$e}] ";
				});
				try {
					$this->chatAPI->sendMessage($this->logRoomId, $tags.$line);
				} catch (\Exception $e) {
					file_put_contents(BASE_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.date('Y_m_d_H_i_s').'.log', $e->getMessage());
				}
			}

			// set last request
			$this->lastRequestTime = $post->creation_date->format('U');
		}

		// end processing
		echo 'Processing finished at: '.date_create()->format('Y-m-d H:i:s').PHP_EOL;
	}
}
