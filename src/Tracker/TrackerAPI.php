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

	private $roomIdCVpls = 215913;

	const LANGS = [
		'es' => 'Spanish',
		'pt' => 'Portuguese',
		'ru' => 'Russian',
		'zh' => 'Chinese',
		'ta' => 'Tamil',
		'ar' => 'Arabic',
		'bn' => 'Bengali',
		'Deva' => 'Devanagari',
		'el' => 'Greek',
		'gu' => 'Gujarati',
		'ko' => 'Korean',
		'id' => 'Indonesian',
		'vi' => 'Vietnamese',
		'fr' => 'French',
		'it' => 'Italian',
		'kn' => 'Kannada',
		'Kana' => 'Katakana',
		'ml' => 'Malayalam',
		'te' => 'Telugu',
		'th' => 'Thai',
	];

	public function __construct(\GuzzleHttp\Client $client, \StackAPI $stackAPI, \ChatAPI $chatAPI, \DotEnv $dotEnv) {
		$this->client = $client;
		$this->chatAPI = $chatAPI;
		$this->stackAPI = $stackAPI;
		
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

		if(DEBUG){
			$this->roomIdCVpls = $this->logRoomId;
		}

		// Say hello
		$this->chatAPI->sendMessage($this->logRoomId, 'TrackerBot started on '.gethostname());
	}

	/**
	 * Entry point. Fetches a bunch of answers and their questions and then parses them.
	 *
	 * @return void
	 */
	public function fetch(string $searchString = null) {
		if ($searchString) {
			$apiEndpoint = 'search/advanced';
		} else {
			$apiEndpoint = 'questions';
		}
		$url = "https://api.stackexchange.com/2.2/" . $apiEndpoint;
		if (DEBUG && !$searchString) {
			$url .= '/60882508';
		}
		$args = [
			'todate' => strtotime('2 minutes ago'),
			'site' => 'stackoverflow',
			'order' => 'asc',
			'sort' => 'creation',
			'pagesize' => '100',
			'page' => '1',
			'filter' => '7yrx3gca'
		];
		if (!DEBUG && !$searchString) {
			$args['fromdate'] = $this->lastRequestTime + 1;
		} else {
			$args['fromdate'] = 0;
		}

		if ($searchString) {
			$args['q'] = $searchString;
			$this->chatAPI->sendMessage($this->roomIdCVpls, 'Started search for: '.$searchString);
		}

		do {
			echo(date_create_from_format('U', (string) $this->lastRequestTime)->format('Y-m-d H:i:s')). ' to '.(date_create_from_format('U', (string) $args['todate'])->format('Y-m-d H:i:s')).PHP_EOL;

			// Request questions
			try {
				$contents = $this->stackAPI->request('GET', $url, $args);
			} catch (\Exception $e) {
				file_put_contents(BASE_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.date('Y_m_d_H_i_s').'.log', $e->getMessage());
			}

			foreach ($contents->items as $postJSON) {
				$post = new Question($postJSON);

				if (!$searchString) {
					// Our rules
					$line = '';
					if (stripos($post->bodyWithTitle, 'mysqli') !== false) {
						$line = "[tag:mysqli]";
					} elseif (preg_match('#mysql_(?:query|connect|select_db|error|fetch|num_rows|escape_string|close|result)#i', $post->bodyWithTitle)) {
						$line = "[tag:mysql_]";
					} elseif (preg_match('#fetch_(?:assoc|array|row|object|num|both|all|field)#i', $post->bodyWithTitle)) {
						$line = "[tag:mysqli]";
					} elseif (stripos($post->bodyWithTitle, '->query') !== false) {
						$line = "[tag:mysqli]";
					} elseif (stripos($post->bodyWithTitle, 'bind_param') !== false) {
						$line = "[tag:mysqli]";
					} elseif (stripos($post->bodyWithTitle, '->error') !== false) {
						$line = "[tag:mysqli]";
					} elseif (in_array('mysqli', $post->tags, true)) {
						$line = "[tag:mysqli]";
					}

					if ($line) {
						$tags = array_reduce($post->tags, function ($carry, $e) {
							return $carry."[tag:{$e}] ";
						});
						try {
							$this->chatAPI->sendMessage($this->logRoomId, $tags.$line." {$post->linkFormatted}".PHP_EOL);
						} catch (\Exception $e) {
							file_put_contents(BASE_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.date('Y_m_d_H_i_s').'.log', $e->getMessage());
						}
					}
				}

				if (!$post->closed_date) {
					if ($lang = $this->checkLanguage($post)) {
						$line = "[tag:cv-pls] ".self::LANGS[$lang]." {$post->linkFormatted}".PHP_EOL;
						try {
							$this->chatAPI->sendMessage($this->roomIdCVpls, $line);
						} catch (\Exception $e) {
							file_put_contents(BASE_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.date('Y_m_d_H_i_s').'.log', $e->getMessage());
						}
					} elseif ($this->noLatinLetters($post)) {
						$line = "[tag:cv-pls] probably non-english {$post->linkFormatted}".PHP_EOL;
						try {
							$this->chatAPI->sendMessage($this->roomIdCVpls, $line);
						} catch (\Exception $e) {
							file_put_contents(BASE_DIR.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'errors'.DIRECTORY_SEPARATOR.date('Y_m_d_H_i_s').'.log', $e->getMessage());
						}
					}
				}

				// set last request
				$this->lastRequestTime = $post->creation_date->format('U');
			}
			$args['page']++;
		} while ($contents->has_more);

		if ($searchString) {
			$this->chatAPI->sendMessage($this->roomIdCVpls, 'Search is over. Quota remaining: '.$contents->quota_remaining);
		}

		// end processing
		echo 'Processing finished at: '.date_create()->format('Y-m-d H:i:s').PHP_EOL;
	}

	private function checkLanguage(Question $post) {
		$langKeywords = [
			'es' => 'codigo|\bpero\b|resultado|\bc(?:o|ó)mo\b|\bHola\b|tengo|ayud(?:a|r?eme)|estoy|Buenos|SALUDO|vamos|Gracias|nuevo|\bAQUI\b|por adelantado|anticipación|¿|¡|\bpued(?:o|a)\b|aplicación|solución|espero|alguien|\buna\b|siguiente',
			'pt' => 'boa tarde|ajude|\btodas\b|\bvoc(?:ê|e)\b|\best(?:á|a)\b|\bcomo\b|vamos|\bestou\b|minha|quando|então|tenho|\bquero\b|\bquem\b|porque|obrigad(?:a|o)|\bJá\b|\bTento\b|\berro\b|(?:de )?dados|\bfunciona\b|Olá|resultou|RESULTADO|Alguma|linha|antecipadamente|dúvida|minha|aplicação|versão|\bpagina\b|\bdois\b|Sou novo',
			'ru' => '\p{Cyrillic}{3,}',
			'ar' => '\p{Arabic}{3,}',
			'bn' => '\p{Bengali}{3,}',
			'zh' => '\p{Han}{3,}',
			'ta' => '\p{Tamil}{3,}', // tamil
			'Deva' => '\p{Devanagari}{3,}',
			'el' => '\p{Greek}{3,}',
			'gu' => '\p{Gujarati}{3,}',
			'ko' => '\p{Hangul}{3,}',
			'kn' => '\p{Kannada}{3,}',
			'Kana' => '\p{Katakana}{3,}',
			'ml' => '\p{Malayalam}{3,}',
			'te' => '\p{Telugu}{3,}',
			'th' => '\p{Thai}{3,}',
			'fr' => 'Bonjour|j\'ai|Merci|problème|Aidez(?:-| )moi|s\'il vous plaît|\baider\b|\bje\b|Erreur|\bavec\b|\bmoi\b|\bsais\b|\bdeux\b|J\'aimerai|\bune\b|j\'essaye|\bvous\b|\bavons\b|création|\bvotre\b|voudrais|\bavoir\b',
			'id' => 'Tolong|Selamat|masalah|bagaimana|\bkapan\b|\bsaya\b|\bsudah\b|Terima kasih|\bjual\b|\bobat\b', //indonesian
			'vi' => 'cảm ơn|Tôi có|xin chào', // vietnamese
			'it' => 'per favore|\baiuto\b|aiutami|Buongiorno|buona serata|io ho|domanda|\bpagina\b', // italian
		];

		$m = [];

		foreach ($langKeywords as $lang => $keywords) {
			if (preg_match_all('#'.$keywords.'#iu', $post->bodyStrippedWithTitle, $matches, PREG_SET_ORDER)) {
				$vals = array_unique(array_column($matches, 0));
				if (count($vals) >= 3) {
					$m[$lang] = $vals;
				}
			}
		}

		if ($m) {
			array_multisort(array_map('count', $m), SORT_DESC, $m);
			return array_keys($m)[0];
		}
	}

	function noLatinLetters($post) {
		if (strlen($post->bodyStrippedWithTitle) < 10) {
			return false;
		}

		preg_match_all(
			'#[a-z]#iu',
			$post->bodyStrippedWithTitle,
			$m1,
		);

		return count(array_unique($m1[0])) <= 5;
	}
}
