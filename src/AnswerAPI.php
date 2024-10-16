<?php

declare(strict_types=1);

use Dharman\ChatAPI;
use Dharman\StackAPI;
use Entities\Natty;
use Entities\Post;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use ParagonIE\EasyDB\EasyDB;

class AnswerAPI {
	use HTHRemovalTrait;

	private EasyDB $db;
	private Client $client;

	/**
	 * Timestamp
	 *
	 * @var mixed
	 */
	private $lastRequestTime;

	/**
	 * Time of last auto-flagging
	 */
	private ?\DateTime $lastFlagTime = null;

	private array $questions = [];

	private const AUTOFLAG_TRESHOLD = 6;
	private const NATTY_FLAG_TRESHOLD = 7;
	private const CHAT_TRESHOLD = 4;

	/**
	 * Token for Dharman user. Secret!
	 */
	private string $userToken = '';

	/**
	 * My app key. Not secret
	 * @var ?string
	 */
	private $app_key = null;

	private ChatAPI $chatAPI;
	private StackAPI $stackAPI;
	private int $logRoomId;
	private ?int $personalRoomId = null;
	private bool $logEdits = false;
	private int $soboticsRoomId = 111347;
	private bool $autoflagging = false;
	private bool $autoediting = false;
	private bool $reportToNatty = false;

	public function __construct(EasyDB $db, \GuzzleHttp\Client $client, StackAPI $stackAPI, ChatAPI $chatAPI, DotEnv $dotEnv) {
		$this->db = $db;
		$this->client = $client;
		$this->chatAPI = $chatAPI;
		$this->stackAPI = $stackAPI;
		$this->lastRequestTime = $this->db->single('SELECT `time` FROM lastRequest');
		if (!$this->lastRequestTime) {
			$this->lastRequestTime = strtotime('15 minutes ago');
		}
		if (DEBUG_OLD) {
			$this->lastRequestTime = strtotime(DEBUG_OLD);
		}

		$this->loadConfig($dotEnv);

		// Say hello
		$this->chatAPI->sendMessage($this->logRoomId, 'v.'.\VERSION.' Started on '.gethostname());
	}

	public function loadConfig(DotEnv $dotEnv) {
		$this->autoflagging = $dotEnv->get('autoflagging');
		$this->autoediting = $dotEnv->get('autoediting');
		$this->reportToNatty = $dotEnv->get('reportToNatty');

		$this->app_key = $dotEnv->get('app_key');

		$this->userToken = $dotEnv->get('key');
		if (!$this->userToken) {
			throw new \Exception('Please login first and provide valid user token!');
		}
		$this->logRoomId = (int) $dotEnv->get('logRoomId');
		if (!$this->logRoomId) {
			throw new \Exception('Please provide valid room ID!');
		}
		$this->personalRoomId = (int) $dotEnv->get('trackRoomId');
		$this->logEdits = (bool) $dotEnv->get('logEdits');
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
			$url .= '/'.DEBUG;
		}
		$args = [
			'key' => $this->app_key,
			'todate' => strtotime('5 minutes ago'),
			'site' => 'stackoverflow',
			'order' => 'asc',
			'sort' => 'creation',
			'filter' => 'Ds7AAhmsA*_R*_GN_PLRT2uskVNwru',
			'access_token' => $this->userToken,
		];
		if (DEBUG) {
			$args['fromdate'] = strtotime('20 years ago');
		} else {
			$args['fromdate'] = $this->lastRequestTime + 1;
		}

		echo date_create_from_format('U', (string) $args['fromdate'])->format('Y-m-d H:i:s') . ' to '.(date_create_from_format('U', (string) $args['todate'])->format('Y-m-d H:i:s')).PHP_EOL;

		// Request answers
		$contents = $this->stackAPI->request('GET', $url, $args);
		if (isset($contents->quota_remaining)) {
			echo "Quota remaining: " . $contents->quota_remaining . "\n";
		}

		// prepare blacklist
		$blacklist = new \Blacklists\Blacklist();
		$whitelist = new \Blacklists\Whitelist();
		$reBlacklist = new \Blacklists\ReBlacklist();

		// Collect question Ids
		$questions = array_column($contents->items, 'question_id');
		$questions = array_unique($questions);
		if ($questions) {
			$this->loadQuestions($questions);
		}

		foreach ($contents->items as $postJSON) {
			$post = new Post($postJSON);
			$h = new \Heuristics($post);
			$reasons = [];
			$triggers = [];
			$score = 0;

			if ($m = $h->CompareAgainstRegexList($blacklist)) {
				$reasons[] = 'Blacklisted phrase:"'.implode('","', array_column($m, 'Word')).'"';
				$score += array_sum(array_column($m, 'Weight'));
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'Blacklisted phrase', 'value' => $bl['Word'], 'weight' => $bl['Weight']];
				}
			}

			if ($m = $h->CompareAgainstRegexList($whitelist)) {
				$reasons[] = 'Whitelisted phrase:"'.implode('","', array_column($m, 'Word')).'"';
				$score += array_sum(array_column($m, 'Weight'));
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'Whitelisted phrase', 'value' => $bl['Word'], 'weight' => $bl['Weight']];
				}
			}

			if ($m = $h->CompareAgainstRegexList($reBlacklist)) {
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
				$weight = 2.5;
				$score += $weight;
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'Me too answer', 'value' => $bl['Word'], 'weight' => $weight];
					$weight = 0;
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
				$weight = 1;
				$score += $weight;
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'User mentioned', 'value' => $bl['Word'], 'weight' => $weight];
					$weight = 0;
				}
			}

			if (isset($this->questions[$post->question_id]['owner']) && $this->questions[$post->question_id]['owner'] === $post->owner->user_id) {
				$reasons[] = 'Self-answer';
				$score += 0.5;
				$triggers[] = ['type' => 'Self-answer', 'weight' => 0.5];
			}

			if ($h->containsNoWhiteSpace()) {
				$reasons[] = 'Has no white space';
				$score += 0.5;
				$triggers[] = ['type' => 'Has no white space', 'weight' => 0.5];
			}

			if ($h->containsNoNewlines()) {
				$reasons[] = 'Single line';
				$score += 0.5;
				$triggers[] = ['type' => 'Single line', 'weight' => 0.5];
			}

			if ($bw = $h->badStart()) {
				$reasons[] = 'Starts with a question:"'.$bw['Word'].'"';
				$score += 0.5;
				$triggers[] = ['type' => 'Starts with a question', 'value' => $bw['Word'], 'weight' => 0.5];
			}

			if ($m = $h->noLatinLetters()) {
				$reasons[] = 'No latin characters';
				$score += $m;
				$triggers[] = ['type' => 'No latin characters', 'weight' => $m];
			}

			if ($m = $h->hasRepeatingChars()) {
				$reasons[] = 'Filler text:"'.implode('","', array_column($m, 'Word')).'"';
				$weight = 0.5;
				$score += $weight;
				foreach ($m as $bl) {
					$triggers[] = ['type' => 'Filler text', 'value' => $bl['Word'], 'weight' => $weight];
					$weight = 0;
				}
			}

			if ($m = $h->lowEntropy()) {
				$reasons[] = 'Low entropy';
				$score += 1;
				$triggers[] = ['type' => 'Low entropy', 'weight' => 1];
			}

			if ($m = $h->looksLikeComment()) {
				$reasons[] = 'Looks like a comment';
				$score += 1;
				$triggers[] = ['type' => 'Looks like a comment', 'weight' => 1];
			}

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
						ErrorHandler::handler($e);
					}
				}
			}

			// while we are at it check if there is fluff to be removed
			if ($score < self::AUTOFLAG_TRESHOLD && $this->autoediting) {
				try {
					$this->removeClutter($post);
				} catch (\Exception $e) {
					ErrorHandler::handler($e);
				}
			}

			// set last request
			$this->lastRequestTime = $post->creation_date->format('U');
		}

		// end processing
		echo 'Processing finished at: '.date_create()->format('Y-m-d H:i:s').PHP_EOL.PHP_EOL;
		// save request time
		if (!DEBUG) {
			try {
				$this->db->run('UPDATE lastRequest SET `time` = ? WHERE rowid=1', $this->lastRequestTime);
			} catch (PDOException $e) {
				// Locked or waiting
				if ($e->errorInfo[1] === 5 || $e->errorInfo[1] === 6) {
					usleep(500000);
					$this->db->run('UPDATE lastRequest SET `time` = ? WHERE rowid=1', $this->lastRequestTime);
				}
			}
		}
	}

	private function loadQuestions(array $questions): void {
		if ($questions) {
			$questionList = implode(';', $questions);
			$url = "https://api.stackexchange.com/2.2/questions/" . $questionList;
			$args = [
				'key' => $this->app_key,
				'site' => 'stackoverflow',
				'order' => 'desc',
				'sort' => 'creation',
				'filter' => '4b*l8uK*lxO_LpAroX(a',
				'access_token' => $this->userToken,
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
	}

	/**
	 * Log the report into a file and chat room.
	 * Flag the post if score is higher or equal to the threshold
	 */
	private function reportAndLog(array $reasons, float $score, Post $post, array $triggers): void {
		$summary = implode('; ', $reasons);
		$line = $post->link.PHP_EOL;
		$line .= $score."\t".$summary.PHP_EOL;

		$shouldBeReportedByNatty = date_create_from_format('U', (string) $this->questions[$post->question_id]['creation_date'])->modify('+ 30 days') < $post->creation_date;
		$nattyStatus = ($shouldBeReportedByNatty && $score >= self::CHAT_TRESHOLD) ? $this->isReportedByNatty($post->id) : new Natty();

		// Report to file
		$this->reportToFile($score, $shouldBeReportedByNatty, $line);

		// report to DB
		if (!DEBUG) {
			$report_id = $this->logToDB($post, $score, $summary, $nattyStatus->score, $triggers);
		} else {
			$report_id = '0';
		}

		if ($score < self::CHAT_TRESHOLD) {
			return;
		}

		// report to Chat
		$reportLink = REPORT_URL.'?id='.$report_id;
		[$flagIcon, $actionTaken] = ['', ''];
		if ($shouldBeReportedByNatty && $nattyStatus->score >= self::NATTY_FLAG_TRESHOLD) {
			// If Natty flagged it, then do nothing. The post was not handled yet...
			[$flagIcon, $actionTaken] = ['🐶', 'Flagged by Natty'];
		} elseif ($score >= self::AUTOFLAG_TRESHOLD) {
			if (!$shouldBeReportedByNatty) {
				['icon' => $flagIcon, 'action' => $actionTaken] = $this->flagPost($post->id);
			} elseif ($nattyStatus->score >= 4 || $nattyStatus->type === "True Negative") {
				// If score is above 7 and Natty was not confident to autoflag then let us flag it unless it is weekend.
				// 31st Aug 2023: Due to decreased activity, let's flag it regardless of score or day of the week
				['icon' => $flagIcon, 'action' => $actionTaken] = $this->flagPost($post->id);
			} else {
				try {
					if ($this->reportToNatty) {
						// Natty missed it, report to Natty in SOBotics and flag the answer
						$reportNatty = "@Natty report https://stackoverflow.com/a/{$post->id}";
						$this->chatAPI->sendMessage($this->soboticsRoomId, $reportNatty);
						$reportNatty = "[Report link]({$reportLink})";
						$this->chatAPI->sendMessage($this->soboticsRoomId, $reportNatty);
					}
				} finally {
					// flag it ourselves
					['icon' => $flagIcon, 'action' => $actionTaken] = $this->flagPost($post->id);
				}
			}

			if ($flagIcon === '🚩') {
				$this->saveFlagToDB($report_id, $post->id);
			}
		}

		$chatLine = '[tag:'.$score.'] [Link to Post]('.$post->link.') [ [Report]('.$reportLink.') ]'." ".($flagIcon ? $flagIcon.' ' : '').$summary;
		if ($actionTaken) {
			$chatLine .= ' — '.$actionTaken;
		}

		if (DEBUG) {
			echo $chatLine;
		} else {
			$this->chatAPI->sendMessage($this->logRoomId, $chatLine);
		}
	}

	private function logToDB(Post $post, float $score, string $summary, ?float $natty_score, array $triggers, int $retries = 0): string {
		try {
			$this->db->beginTransaction();
			$report_id = $this->db->insertReturnId(
				'reports',
				[
					'answer_id' => $post->id,
					'body' => $post->bodySafe,
					'score' => $score,
					'natty_score' => $natty_score,
					'summary' => $summary,
					'reported_at' => date('Y-m-d H:i:s'),
					'user_id' => $post->owner->user_id,
					'username' => $post->owner->display_name,
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
			$this->db->commit();
		} catch (PDOException $e) {
			$this->db->rollBack();
			// Locked or waiting
			if ($retries >= 5 || ($e->errorInfo[1] !== 5 && $e->errorInfo[1] !== 6)) {
				throw $e;
			}
			return $this->logToDB($post, $score, $summary, $natty_score, $triggers, $retries + 1);
		}

		return $report_id;
	}

	/**
	 * Calls Natty API to see if Natty has a report for this answer.
	 * If there is no report or the score was lower than Natty's threshold then it means it was not reported.
	 */
	private function isReportedByNatty(int $answerId): Natty {
		$rq = $this->client->get('https://logs.sobotics.org/napi/api/feedback/'.$answerId);
		$nattyJSON = json_decode($rq->getBody()->getContents());
		return new Natty($nattyJSON->items[0]->naaValue ?? null, $nattyJSON->items[0]->type ?? null);
	}

	/**
	 * Calls Stack API to get possible flag options and then cast NAA flag
	 *
	 * @return string[]
	 */
	private function flagPost(int $answer_id): array {
		if (!$this->autoflagging) {
			return ['icon' => '🏳️', 'action' => '@Dharman Autoflagging is switched off'];
		}

		// throttle
		if ($this->lastFlagTime && $this->lastFlagTime >= ($now = date_create('5 seconds ago'))) {
			sleep($now->diff($this->lastFlagTime)->s + 1);
			echo 'Slept: '. $now->diff($this->lastFlagTime)->s .' seconds'.PHP_EOL;
		}
		$this->lastFlagTime = new DateTime();

		$url = 'https://api.stackexchange.com/2.2/answers/'.$answer_id.'/flags/options';

		$args = [
			'key' => $this->app_key,
			'site' => 'stackoverflow',
			'access_token' => $this->userToken, // Dharman
		];

		// Get flag options
		try {
			$contentsJSON = $this->stackAPI->request('GET', $url, $args);
		} catch (ClientException $e) {
			ErrorHandler::handler($e);
			return ['icon' => '🏳️', 'action' => 'Error calling API'];
		}

		$option_id = null;
		foreach ($contentsJSON->items as $option) {
			if (mb_strtolower($option->title) == 'not an answer') {
				$option_id = $option->option_id;
				break;
			}
		}

		if (!$option_id) {
			return ['icon' => '🏳️', 'action' => '@Dharman Flagging as NAA not possible'];
		}

		$url = 'https://api.stackexchange.com/2.2/answers/'.$answer_id.'/flags/add';

		$args += [
			'option_id' => $option_id,
			'preview' => true
		];

		// Cast NAA flag
		try {
			$this->stackAPI->request('POST', $url, $args);
		} catch (ClientException $e) {
			$response = $e->getResponse();
			if ($response && false !== strpos(json_decode((string) $response->getBody())->error_message, 'already flagged')) {
				return ['icon' => '', 'action' => 'Already manually flagged'];
			} else {
				ErrorHandler::handler($e);
				return ['icon' => '🏳️', 'action' => 'Error calling API'];
			}
		}

		return ['icon' => '🚩', 'action' => 'Post auto-flagged'];
	}

	private function saveFlagToDB(string $report_id, int $answer_id): void {
		try {
			$this->db->insert(
				'flags',
				[
					'report_id' => $report_id,
					'answer_id' => $answer_id,
					'created_at' => date('Y-m-d H:i:s')
				]
			);
		} catch (PDOException $e) {
			ErrorHandler::handler($e); // we ignore exceptions, but we still log them
		}
	}

	private function reportToFile(float $score, bool $shouldBeReportedByNatty, string $line): void {
		if ($score >= self::AUTOFLAG_TRESHOLD) {
			if ($shouldBeReportedByNatty) {
				file_put_contents(BASE_DIR . '/logs/log_NATTY.txt', $line, FILE_APPEND);
			} else {
				file_put_contents(BASE_DIR . '/logs/log_autoflagged.txt', $line, FILE_APPEND);
			}
		} elseif ($score >= self::CHAT_TRESHOLD) {
			file_put_contents(BASE_DIR . '/logs/log_3.txt', $line, FILE_APPEND);
		} elseif ($score >= 2.5) {
			file_put_contents(BASE_DIR . '/logs/log_2_5.txt', $line, FILE_APPEND);
		} elseif ($score >= 1) {
			file_put_contents(BASE_DIR . '/logs/log_low.txt', $line, FILE_APPEND);
		} else {
			file_put_contents(BASE_DIR . '/logs/log_lessthan1.txt', $line, FILE_APPEND);
		}
	}
}
