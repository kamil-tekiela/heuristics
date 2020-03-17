<?php

declare(strict_types=1);

use ParagonIE\EasyDB\EasyDB;

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

class APIFetcher {
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
	private $lastRequestTime;

	/**
	 * Time of last auto-flagging
	 *
	 * @var \DateTime
	 */
	private $lastFlagTime = null;

	private $questions = [];
	
	private const AUTOFLAG_TRESHOLD = 5;

	/**
	 * My app ket. Not secret
	 */
	private const APP_KEY = 'gS)WzUg0j7Q5ZVEBB5Onkw((';

	/**
	 * Token for Dharman user. Secret!
	 *
	 * @var string
	 */
	private $key_me = '';

	/**
	 * Token for TagBot. Secret!
	 *
	 * @var string
	 */
	private $key_bot = '';

	public function __construct(EasyDB $db, \GuzzleHttp\Client $client) {
		$this->db = $db;
		$this->client = $client;
		$this->lastRequestTime = $this->db->single('SELECT `time` FROM lastRequest');
		if (!$this->lastRequestTime) {
			$this->lastRequestTime = strtotime('15 minutes ago');
		}
		if (DEBUG) {
			$this->lastRequestTime = strtotime('15 days ago');
		}

		$config = parse_ini_file(BASE_DIR.'/config.ini');
		$this->key_me = $config['key_me'];
		$this->key_bot = $config['key_bot'];
	}

	public function fetch() {
		$apiEndpoint = 'answers';
		$url = "https://api.stackexchange.com/2.2/" . $apiEndpoint;
		if (DEBUG) {
			$url .= '/60765207';
		}
		$args = [
			// 'page' => $currentPage,
			// 'pagesize' => $pageSize,
			'key' => self::APP_KEY,
			'todate' => strtotime('5 minutes ago'),
			'site' => 'stackoverflow',
			'order' => 'asc',
			'sort' => 'creation',
			'filter' => 'eA4nsiKhnQiMog4It1'
		];
		$args['fromdate'] = $this->lastRequestTime + 1;

		echo(date_create_from_format('U', (string) $args['fromdate'])->format('Y-m-d H:i:s')). ' to '.(date_create_from_format('U', (string) $args['todate'])->format('Y-m-d H:i:s')).PHP_EOL;

		try {
			// if (DEBUG) {
			// 	$json_contents = file_get_contents('./data.json');
			// } else {
			$rq = $this->client->request('GET', $url, ['query' => $args]);
			$json_contents = $rq->getBody()->getContents();
			file_put_contents('data.json', $json_contents);
			// }
		} catch (RequestException $e) {
			throw new Exception(Psr7\str($e->getResponse()));
		}

		$contents = json_decode($json_contents);

		// prepare blacklist
		$blacklist = new Blacklist($this->db);

		// Get questions
		$questions = [];
		foreach ($contents->items as $postJSON) {
			$questions[] = $postJSON->question_id;
		}
		if ($questions) {
			$questionList = implode(';', $questions);
			$url = "https://api.stackexchange.com/2.2/questions/" . $questionList;
			$args = [
				'key' => self::APP_KEY,
				'site' => 'stackoverflow',
				'order' => 'desc',
				'sort' => 'creation',
				'filter' => '4b*l8uK*lxO_LpAroX(a'
			];
			try {
				$rq = $this->client->request('GET', $url, ['query' => $args]);
				$json_contents = $rq->getBody()->getContents();
				$qsJSON = json_decode($json_contents);
				$this->questions = [];
				foreach ($qsJSON->items as $postJSON) {
					if (isset($postJSON->owner->user_id)) {
						$this->questions[$postJSON->question_id]['owner'] = $postJSON->owner->user_id;
					}
					$this->questions[$postJSON->question_id]['creation_date'] = $postJSON->creation_date;
				}
			} catch (RequestException $e) {
				throw new Exception(Psr7\str($e->getResponse()));
			}
		}

		foreach ($contents->items as $postJSON) {
			$post = new \Post($postJSON);
			$h = new Heuristics($this->db, $post);
			$reasons = [];
			$score = 0;
			if ($m = $h->CompareAgainstBlacklist($blacklist)) {
				$reasons[] = 'Blacklisted phrase:"'.implode('","', array_column($m, 'Word')).'"';
				$score += array_sum(array_column($m, 'Weight'));
				// $score += count($m);
			}
			if ($h->HighLinkProportion()) {
				$reasons[] = 'Probably link only';
				$score += 1;
			}
			if ($h->ContainsSignature()) {
				$reasons[] = 'Contains signature';
				$score += 1;
			}
			if ($lenFactor = $h->PostLengthUnderThreshold()) {
				$reasons[] = 'Low length';
				$score += $lenFactor;
			}
			if ($m = $h->MeTooAnswer()) {
				$reasons[] = 'Me too answer:"'.implode('","', array_column($m, 'Word')).'"';
				$score += count($m) * 2;
			}
			if ($m = $h->endsInQuestion()) {
				$reasons[] = 'Ends in question mark';
				$score += 2.0;
			} elseif ($m = $h->containsQuestion()) {
				$reasons[] = 'Contains question mark';
				$score += 0.5;
			}
			if ($post->owner->user_type === 'unregistered') {
				$reasons[] = 'Unregistered user';
				$score += 0.5;
			}
			if ($m = $h->userMentioned()) {
				$reasons[] = 'User mentioned:"'.implode('","', array_column($m, 'Word')).'"';
				$score += 1; // false positives are present. Cap to 1
			}
			if (isset($this->questions[$postJSON->question_id]['owner'], $postJSON->owner->user_id)) {
				if (isset($this->questions[$postJSON->question_id]['owner']) && $this->questions[$postJSON->question_id]['owner'] === $postJSON->owner->user_id) {
					$reasons[] = 'Self-answer';
					$score += 0.5;
				}
			}
			if ($h->containsNoWhiteSpace()) {
				$reasons[] = 'Has no white space';
				$score += 0.5;
			}
			if ($m = $h->badStart()) {
				$reasons[] = 'Starts with a question:"'.implode('","', array_column($m, 'Word')).'"';
				$score += count($m) * 1;
			}

			if ($reasons) {
				if ($repFactor = $h->OwnerRepFactor()) {
					if ($repFactor > 0) {
						$reasons[] = 'Low reputation';
					}
					$score += $repFactor;
				}
				if ($score > 0) {
					$this->reportAndLog($reasons, $score, $post);
				}
			}

			// set last request
			$this->lastRequestTime = $post->creation_date->format('U');
		}

		// end processing
		echo 'Processing finished at: '.date_create()->format('Y-m-d H:i:s').PHP_EOL;
		// save request time
		if (!DEBUG) {
			$this->db->run('UPDATE lastRequest SET `time` = ? WHERE rowid=1', $this->lastRequestTime);
		}
	}

	private function reportAndLog(array $reasons, float $score, \Post $post) {
		$line = $post->link.PHP_EOL;
		$line .= $score."\t".implode(';', $reasons).PHP_EOL;
		if (DEBUG) {
			echo $line;
		} else {
			$shoudBeReportedByNatty = date_create_from_format('U', (string) $this->questions[$post->question_id]['creation_date'])->modify('+ 1 month') < $post->creation_date;
			if ($score >= self::AUTOFLAG_TRESHOLD) {
				if ($shoudBeReportedByNatty) {
					file_put_contents('log_NATTY.txt', $line, FILE_APPEND);
				} else {
					file_put_contents('log_autoflagged.txt', $line, FILE_APPEND);
					if (!DEBUG) {
						$this->flagPost($post->id);
					}
				}
			} elseif ($score >= 4) {
				file_put_contents('log_3.txt', $line, FILE_APPEND);
			} elseif ($score >= 2.5) {
				file_put_contents('log_2_5.txt', $line, FILE_APPEND);
			} elseif ($score >= 1) {
				file_put_contents('log_low.txt', $line, FILE_APPEND);
			} else {
				file_put_contents('log_lessthan1.txt', $line, FILE_APPEND);
			}
		}
	}

	private function flagPost(int $question_id) {
		// throttle
		if ($this->lastFlagTime && $this->lastFlagTime >= ($now = date_create('5 seconds ago'))) {
			sleep($now->diff($this->lastFlagTime)->s + 1);
			echo 'Slept: '. $now->diff($this->lastFlagTime)->s .' seconds'.PHP_EOL;
		}

		$url = 'https://api.stackexchange.com/2.2/answers/'.$question_id.'/flags/options';

		$args = [
			'site' => 'stackoverflow',
			'access_token' => $this->key_bot, // TagBot
			// 'access_token' => $this->key_me, // Dharman
			'key' => self::APP_KEY
		];

		$rq = $this->client->request('GET', $url, ['http_errors' => false, 'query' => $args]);

		$options = json_decode($rq->getBody()->getContents())->items;

		$option_id = null;
		foreach ($options as $option) {
			if ($option->title == 'not an answer') {
				$option_id = $option->option_id;
				break;
			}
		}

		$url = 'https://api.stackexchange.com/2.2/answers/'.$question_id.'/flags/add';

		$args += [
			'option_id' => $option_id,
			// 'comment' => 'Testing write access.',
			'preview' => true
		];

		$rq = $this->client->request('POST', $url, ['http_errors' => false, 'form_params' => $args]);

		if ($rq->getStatusCode() != 200) {
			var_dump($rq->getBody()->getContents());
		}

		$this->lastFlagTime = new DateTime();
	}
}
