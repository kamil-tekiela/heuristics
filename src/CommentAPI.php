<?php

use ParagonIE\EasyDB\EasyDB;

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

class CommentAPI {
	/**
	 * DB link
	 *
	 * @var EasyDB
	 */
	private $db;

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
	private $lastRequest;

	/**
	 * Time of last auto-flagging
	 *
	 * @var \DateTime
	 */
	private $lastFlagTime = null;

	/**
	 * My app key. Not secret
	 */
	private const APP_KEY = 'gS)WzUg0j7Q5ZVEBB5Onkw((';

	/**
	 * Token for Dharman user. Secret!
	 *
	 * @var string
	 */
	private $userToken = '';

	public $running_count = 0;

	public function __construct(EasyDB $db, \GuzzleHttp\Client $client, string $delay, DotEnv $dotEnv) {
		$this->db = $db;
		$this->client = $client;
		$this->lastRequest = strtotime($delay);
		$this->userToken = $dotEnv->get('key');
		if (!$this->userToken) {
			throw new \Exception('Please login first and provide valid user token!');
		}
	}

	public function fetch() {
		$apiEndpoint = 'comments';
		$url = "https://api.stackexchange.com/2.2/" . $apiEndpoint;
		// if (DEBUG) {
		// 	$url .= '/60673041';
		// }
		$args = [
			'key' => self::APP_KEY,
			// 'todate' => strtotime('4 days 15 hours ago'),
			'site' => 'stackoverflow',
			'order' => 'asc',
			'sort' => 'creation',
			'filter' => ')bG2qRHtCqMZR'
		];
		if ($this->lastRequest) {
			$args['fromdate'] = $this->lastRequest + 1;
		}

		try {
			$rq = $this->client->request('GET', $url, ['query' => $args]);
			$json_contents = $rq->getBody()->getContents();
		} catch (RequestException $e) {
			throw new Exception(Psr7\str($e->getResponse()));
		}

		$contents = json_decode($json_contents);

		// Apply heuristics
		foreach ($contents->items as $commentJSON) {
			$comment = new Comment($commentJSON);

			$reasons = [];

			$reasons = array_merge($reasons, $comment->getGratitude());
			$reasons = array_merge($reasons, $comment->itWorked());
			$reasons = array_merge($reasons, $comment->yourWelcome());
			$reasons = array_merge($reasons, $comment->youHelpedMe());
			$reasons = array_merge($reasons, $comment->updated());
			$reasons = array_merge($reasons, $comment->excitement());
			$reasons = array_merge($reasons, $comment->lifeSaver());
			if ($reasons) {
				$reasons = array_merge($reasons, $comment->userMentioned());
			}

			if ($reasons && ($ratio = $comment->noiseToSize($reasons)) > 0.33) {
				$line = $comment->creation_date->format('Y-m-d H:i:s').' - '.$comment->link.PHP_EOL;
				$line .= $comment->body.PHP_EOL;
				$line .= round($ratio, 2)."\t";
				$line .= 'Reasons: '.implode(', ', $reasons).PHP_EOL.PHP_EOL;
				if ($ratio >= 0.75) {
					file_put_contents('comments.txt', $line, FILE_APPEND);
					if (!DEBUG) {
						$this->flagPost($comment->id);
					}
				} else {
					file_put_contents('comments_log.txt', $line, FILE_APPEND);
				}
			}

			// set last request
			$this->lastRequest = $commentJSON->creation_date;
		}
	}

	private function flagPost(int $question_id) {
		// throttle
		if ($this->lastFlagTime && $this->lastFlagTime >= ($now = date_create('5 seconds ago'))) {
			sleep($now->diff($this->lastFlagTime)->s + 1); // sleep at least a second
		}

		$url = 'https://api.stackexchange.com/2.2/comments/'.$question_id.'/flags/options';

		$args = [
			'site' => 'stackoverflow',
			'access_token' => $this->userToken, // Dharman
			'key' => self::APP_KEY
		];

		$rq = $this->client->request('GET', $url, ['http_errors' => false, 'query' => $args]);

		$options = json_decode($rq->getBody()->getContents())->items;

		$option_id = null;
		foreach ($options as $option) {
			if ($option->title == 'It\'s no longer needed.') {
				$option_id = $option->option_id;
				break;
			}
		}

		$url = 'https://api.stackexchange.com/2.2/comments/'.$question_id.'/flags/add';

		$args += [
			'option_id' => $option_id,
			'preview' => true
		];

		$rq = $this->client->request('POST', $url, ['http_errors' => false, 'form_params' => $args]);
		
		$this->running_count++;
		$this->lastFlagTime = new DateTime();
	}
}
