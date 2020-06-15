<?php

declare(strict_types=1);

use ParagonIE\EasyDB\EasyDB;

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

class AnswerAPI {
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
	
	private const AUTOFLAG_TRESHOLD = 6;

	private const NATTY_FLAG_TRESHOLD = 7;

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

	private $soboticsRoomId = 111347;

	private $pingOwner = '';

	public function __construct(EasyDB $db, \GuzzleHttp\Client $client, StackAPI $stackAPI, ChatAPI $chatAPI, DotEnv $dotEnv) {
		$this->db = $db;
		$this->client = $client;
		$this->chatAPI = $chatAPI;
		$this->stackAPI = $stackAPI;
		$this->lastRequestTime = $this->db->single('SELECT `time` FROM lastRequest');
		if (!$this->lastRequestTime) {
			$this->lastRequestTime = strtotime('15 minutes ago');
		}
		// if (DEBUG) {
		// 	$this->lastRequestTime = strtotime('15 days ago');
		// }

		$this->pingOwner = $dotEnv->get('pingOwner');

		$this->userToken = $dotEnv->get('key');
		if (!$this->userToken) {
			throw new \Exception('Please login first and provide valid user token!');
		}
		$this->logRoomId = (int) $dotEnv->get('logRoomId');
		if (!$this->logRoomId) {
			throw new \Exception('Please provide valid room ID!');
		}

		// Say hello
		$this->chatAPI->sendMessage($this->logRoomId, 'v.'.\VERSION.' Started on '.gethostname());
	}

	/**
	 * Entry point. Fetches a bunch of answers and their questions and then parses them.
	 *
	 * @return void
	 */
	public function fetch() {
		$apiEndpoint = 'answers';
		$url = "https://api.stackexchange.com/2.2/" . $apiEndpoint;
		if (DEBUG) {
			$url .= '/61433893';
		}
		$args = [
			'todate' => strtotime('5 minutes ago'),
			'site' => 'stackoverflow',
			'order' => 'asc',
			'sort' => 'creation',
			'filter' => 'eA4nsiKhnQiMog4It1'
		];
		if (DEBUG) {
			$args['fromdate'] = strtotime('5 years ago');
		} else {
			$args['fromdate'] = $this->lastRequestTime + 1;
		}

		echo(date_create_from_format('U', (string) $args['fromdate'])->format('Y-m-d H:i:s')). ' to '.(date_create_from_format('U', (string) $args['todate'])->format('Y-m-d H:i:s')).PHP_EOL;

		// Request answers
		$contents = $this->stackAPI->request('GET', $url, $args);

		// prepare blacklist
		$blacklist = new \Blacklists\Blacklist();
		$whitelist = new \Blacklists\Whitelist();
		$reBlacklist = new \Blacklists\ReBlacklist();

		// Collect question Ids
		$questions = [];
		foreach ($contents->items as $postJSON) {
			$questions[] = $postJSON->question_id;
		}
		$questions = array_unique($questions);
		if ($questions) {
			$questionList = implode(';', $questions);
			$url = "https://api.stackexchange.com/2.2/questions/" . $questionList;
			$args = [
				'site' => 'stackoverflow',
				'order' => 'desc',
				'sort' => 'creation',
				'filter' => '4b*l8uK*lxO_LpAroX(a'
			];

			// Get questions
			$questionsJSON = $this->stackAPI->request('GET', $url, $args);

			$this->questions = [];
			foreach ($questionsJSON->items as $postJSON) {
				if (isset($postJSON->owner->user_id)) {
					$this->questions[$postJSON->question_id]['owner'] = $postJSON->owner->user_id;
				}
				$this->questions[$postJSON->question_id]['creation_date'] = $postJSON->creation_date;
			}
		}

		foreach ($contents->items as $postJSON) {
			$post = new \Post($postJSON);
			$h = new \Heuristics($this->db, $post);
			$reasons = [];
			$triggers = [];
			$score = 0;

			if ($m = $h->CompareAgainstBlacklist($blacklist)) {
				$reasons[] = 'Blacklisted phrase:"'.implode('","', array_column($m, 'Word')).'"';
				$score += array_sum(array_column($m, 'Weight'));
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'Blacklisted phrase', 'value' => $bl['Word'], 'weight' => $bl['Weight']];
				}
			}
			if ($m = $h->CompareAgainstBlacklist($whitelist)) {
				$reasons[] = 'Whitelisted phrase:"'.implode('","', array_column($m, 'Word')).'"';
				$score += array_sum(array_column($m, 'Weight'));
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'Whitelisted phrase', 'value' => $bl['Word'], 'weight' => $bl['Weight']];
				}
			}

			if ($m = $h->regexBlacklist($reBlacklist)) {
				$reasons[] = 'RegEx Blacklisted phrase:"'.implode('","', array_column($m, 'Word')).'"';
				$score += array_sum(array_column($m, 'Weight'));
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'RegEx Blacklisted phrase', 'value' => $bl['Word'], 'weight' => $bl['Weight']];
				}
			}

			if ($h->HighLinkProportion()) {
				$reasons[] = 'Probably link only';
				$score += 1;
				$triggers[] = ['type' => 'Probably link only', 'weight' => 1];
			}
			if ($h->ContainsSignature()) {
				$reasons[] = 'Contains signature';
				$score += 1;
				$triggers[] = ['type' => 'Contains signature', 'weight' => 1];
			}
			if ($lenFactor = $h->PostLengthUnderThreshold()) {
				$lenFactor = floor($lenFactor * 2) / 2; // round to nearest half
				if ($lenFactor > 0) {
					$reasons[] = 'Low length ('.$lenFactor.')';
					$triggers[] = ['type' => 'Low length', 'weight' => $lenFactor];
				} else {
					$reasons[] = 'Long answer ('.$lenFactor.')';
					$triggers[] = ['type' => 'Long answer', 'weight' => $lenFactor];
				}
				$score += $lenFactor;
			}
			if ($h->hasNoCode()) {
				$reasons[] = 'No code block';
				$score += 0.5;
				$triggers[] = ['type' => 'No code block', 'weight' => 0.5];
			} else {
				$score += -0.5;
				$triggers[] = ['type' => 'Has code block', 'weight' => -0.5];
			}
			if ($m = $h->MeTooAnswer()) {
				$reasons[] = 'Me too answer:"'.implode('","', array_column($m, 'Word')).'"';
				// $score += count($m) * 2;
				$score += 2;
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'Me too answer', 'value' => $bl['Word'], 'weight' => 2];
				}
			}
			if ($m = $h->endsInQuestion()) {
				$reasons[] = 'Ends in question mark';
				$score += 2.0;
				$triggers[] = ['type' => 'Ends in question mark', 'weight' => 2];
			} elseif ($m = $h->containsQuestion()) {
				$reasons[] = 'Contains question mark';
				$score += 0.5;
				$triggers[] = ['type' => 'Contains question mark', 'weight' => 0.5];
			}
			if ($post->owner->user_type === 'unregistered') {
				$reasons[] = 'Unregistered user';
				$score += 0.5;
				$triggers[] = ['type' => 'Unregistered user', 'weight' => 0.5];
			}
			if ($m = $h->userMentioned()) {
				$reasons[] = 'User mentioned:"'.implode('","', array_column($m, 'Word')).'"';
				$score += 1; // false positives are present. Cap to 1
				$triggers[] = ['type' => 'User mentioned', 'value' => implode('","', array_column($m, 'Word')), 'weight' => 1];
			}
			if (isset($this->questions[$postJSON->question_id]['owner'], $postJSON->owner->user_id)) {
				if (isset($this->questions[$postJSON->question_id]['owner']) && $this->questions[$postJSON->question_id]['owner'] === $postJSON->owner->user_id) {
					$reasons[] = 'Self-answer';
					$score += 0.5;
					$triggers[] = ['type' => 'Self-answer', 'weight' => 0.5];
				}
			}
			if ($h->containsNoWhiteSpace()) {
				$reasons[] = 'Has no white space';
				$score += 0.5;
				$triggers[] = ['type' => 'Has no white space', 'weight' => 0.5];
			}
			if ($m = $h->badStart()) {
				$reasons[] = 'Starts with a question:"'.implode('","', array_column($m, 'Word')).'"';
				$score += 0.5;
				$triggers[] = ['type' => 'Starts with a question', 'value' => implode('","', array_column($m, 'Word')), 'weight' => 0.5];
			}
			if ($m = $h->noLatinLetters()) {
				$reasons[] = 'No latin characters';
				$score += 3.0;
				$triggers[] = ['type' => 'No latin characters', 'weight' => 3];
			}
			if ($m = $h->hasRepeatingChars()) {
				$reasons[] = 'Filler text:"'.implode('","', array_column($m, 'Word')).'"';
				$score += 0.5;
				$triggers[] = ['type' => 'Filler text', 'value' => implode('","', array_column($m, 'Word')), 'weight' => 0.5];
			}
			// if ($m = $h->thanksInAdvance()) {
			// 	$reasons[] = 'Gratitude:"'.implode('","', array_column($m, 'Word')).'"';
			// 	$score += 3.0;
			// 	$triggers[] = ['type' => 'Gratitude', 'value' => implode('","', array_column($m, 'Word')), 'weight' => 3.0];
			// }

			if ($reasons) {
				if ($repFactor = $h->OwnerRepFactor()) {
					if ($repFactor > 0) {
						$reasons[] = 'Low reputation';
						$triggers[] = ['type' => 'Low reputation', 'weight' => $repFactor];
					} else {
						$triggers[] = ['type' => 'High reputation', 'weight' => $repFactor];
					}
					$score += $repFactor;
				}
				if ($score > 0) {
					try {
						$this->reportAndLog($reasons, $score, $post, $triggers);
					} catch (\Exception $e) {
						file_put_contents(BASE_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.date('Y_m_d_H_i_s').'.log', $e->getMessage());
					}
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

	/**
	 * Log the report into a file and chat room.
	 * Flag the post if score is higher or equal to the threshold
	 *
	 * @param array $reasons
	 * @param float $score
	 * @param \Post $post
	 * @return void
	 */
	private function reportAndLog(array $reasons, float $score, \Post $post, array $triggers) {
		$natty_score = null;
		$summary = implode(';', $reasons);
		$line = $post->link.PHP_EOL;
		$line .= $score."\t".$summary.PHP_EOL;
		if (DEBUG) {
			echo $line;
			return;
		}
		
		// Report to file
		$shoudBeReportedByNatty = date_create_from_format('U', (string) $this->questions[$post->question_id]['creation_date'])->modify('+ 30 days') < $post->creation_date;
		if ($score >= self::AUTOFLAG_TRESHOLD) {
			if ($shoudBeReportedByNatty) {
				$natty_score = $this->isReportedByNatty($post->id);
				file_put_contents(BASE_DIR.'/logs/log_NATTY.txt', $line, FILE_APPEND);
			} else {
				file_put_contents(BASE_DIR.'/logs/log_autoflagged.txt', $line, FILE_APPEND);
				if (!DEBUG) {
					$this->flagPost($post->id);
				}
			}
		} elseif ($score >= 4) {
			file_put_contents(BASE_DIR.'/logs/log_3.txt', $line, FILE_APPEND);
		} elseif ($score >= 2.5) {
			file_put_contents(BASE_DIR.'/logs/log_2_5.txt', $line, FILE_APPEND);
		} elseif ($score >= 1) {
			file_put_contents(BASE_DIR.'/logs/log_low.txt', $line, FILE_APPEND);
		} else {
			file_put_contents(BASE_DIR.'/logs/log_lessthan1.txt', $line, FILE_APPEND);
		}

		// report to DB
		if (!DEBUG) {
			// log to DB
			$report_id = $this->logToDB($post, $score, $summary, $natty_score, $triggers);
		} else {
			$report_id = 0;
		}

		// report to Chat
		if ($score >= 4) {
			$chatLine = '[tag:'.$score.'] [Link to Post]('.$post->link.') [ [Report]('.REPORT_URL.'?id='.$report_id.') ]'."\t".implode('; ', $reasons);
			$this->chatAPI->sendMessage($this->logRoomId, $chatLine);
			
			if ($score >= self::AUTOFLAG_TRESHOLD) {
				$chatLine = 'Post auto-flagged.';
				if ($shoudBeReportedByNatty) {
					if ($natty_score >= self::NATTY_FLAG_TRESHOLD) {
						// If Natty flagged it, then do nothing. The post was not handled yet...
						$chatLine = 'Post would have been auto-flagged, but flagged by Natty instead.';
					} elseif ($natty_score >= 4) {
						// If score is above 7 and Natty was not confident to autoflag then let us flag it unless it is weekend.
						if ($score >= 7 || date('N') >= 6) {
							if (!DEBUG) {
								$this->flagPost($post->id);
							}
						} else {
							$chatLine = 'Not flagged, because I am skimpy';
						}
					} else {
						if (!DEBUG) {
							// Natty missed it, report to Natty in SOBotics and flag the answer
							$reportNatty = '@Natty report https://stackoverflow.com/a/'.$post->id;
							$this->chatAPI->sendMessage($this->soboticsRoomId, $reportNatty);
							$reportLink = REPORT_URL.'?id='.$report_id;
							if ($this->pingOwner) {
								$reportLink .= ' @'.$this->pingOwner;
							}
							$this->chatAPI->sendMessage($this->soboticsRoomId, 'Reported in '.$reportLink);
							$this->flagPost($post->id);
						}
					}
				}
				$this->chatAPI->sendMessage($this->logRoomId, $chatLine);
			}
		}
	}

	private function logToDB(Post $post, float $score, string $summary, ?float $natty_score, array $triggers) {
		$report_id = $this->db->insertReturnId(
			'reports',
			[
				'answer_id' => $post->id,
				'body' => $post->body,
				'score' => $score,
				'natty_score' => $natty_score,
				'summary' => $summary,
				'reported_at' => date('Y-m-d H:i:s'),
				'user_id' => ($post->owner->user_id ?? null),
				'username' => ($post->owner->display_name ?? null),
			]
		);

		foreach ($triggers as $trigger) {
			$this->db->insert('reasons', [
				'report_id' => $report_id,
				'type' => $trigger['type'] ?? null,
				'value' => $trigger['value'] ?? null,
				'weight' => $trigger['weight'] ?? null
			]);
		}
		
		return $report_id;
	}

	/**
	 * Calls Natty API to see if Natty has a report for this answer.
	 * If there is no report or the score was lower than Natty's threshold then it means it was not reported.
	 *
	 * @param integer $answerId
	 * @return boolean
	 */
	private function isReportedByNatty(int $answerId): ?float {
		$rq = $this->client->get('https://logs.sobotics.org/napi/api/feedback/'.$answerId);
		$nattyJSON = json_decode($rq->getBody()->getContents());
		return $nattyJSON->items[0]->naaValue ?? null;
	}

	/**
	 * Calls Stack API to get possible flag options and then cast NAA flag
	 *
	 * @param integer $question_id
	 * @return void
	 */
	private function flagPost(int $question_id) {
		// throttle
		if ($this->lastFlagTime && $this->lastFlagTime >= ($now = date_create('5 seconds ago'))) {
			sleep($now->diff($this->lastFlagTime)->s + 1);
			echo 'Slept: '. $now->diff($this->lastFlagTime)->s .' seconds'.PHP_EOL;
		}

		$url = 'https://api.stackexchange.com/2.2/answers/'.$question_id.'/flags/options';

		$args = [
			'site' => 'stackoverflow',
			'access_token' => $this->userToken, // Dharman
		];

		// Get flag options
		$contentsJSON = $this->stackAPI->request('GET', $url, $args);

		$option_id = null;
		foreach ($contentsJSON->items as $option) {
			if ($option->title == 'not an answer') {
				$option_id = $option->option_id;
				break;
			}
		}

		if (!$option_id) {
			return;
		}

		$url = 'https://api.stackexchange.com/2.2/answers/'.$question_id.'/flags/add';

		$args += [
			'option_id' => $option_id,
			'preview' => true
		];

		// Cast NAA flag
		$contentsJSON = $this->stackAPI->request('POST', $url, $args);

		$this->lastFlagTime = new DateTime();
	}
}
