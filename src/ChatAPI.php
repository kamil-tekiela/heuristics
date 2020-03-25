<?php

use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;

class ChatAPI {
	private $cookieJarName = 'chatAPI_cookies.json';

	private $siteUrl = 'https://stackoverflow.com';

	/**
	 * Guzzle client
	 *
	 * @var GuzzleHttp\Client
	 */
	private $client = null;

	private $chatFKey = '';

	private $roomID = '';

	public function __construct($roomID) {
		if (!$roomID) {
			throw new \Exception('No room ID given!');
		}
		$this->roomID = (string) $roomID;

		$sessionCookieJar = new \GuzzleHttp\Cookie\FileCookieJar($this->cookieJarName, true);
		$this->client = new GuzzleHttp\Client([
			'cookies' => $sessionCookieJar
		]);

		$loginPage = $this->siteUrl.'/users/login';
		$rq = $this->client->request('GET', $loginPage, [
			'cookies' => $sessionCookieJar,
			'allow_redirects' => false,
		]);
	
		if ($rq->getStatusCode() == 200) {
			$rq = $this->client->get($loginPage);
			$fkey = $this->getFKey($rq->getBody()->getContents());

			$rq = $this->client->request('POST', $loginPage, [
				'form_params' => [
					'fkey' => $fkey,
					'email' => '9tagbot9@gmail.com',
					'password' => 'Dharman246'
				]
			]);
			var_dump('Logged in!');
			$sessionCookieJar->save($this->cookieJarName);
		}

		//get fkey for chat
		$rq = $this->client->get('https://chat.stackoverflow.com/rooms/'.$this->roomID);
		$this->chatFKey = $this->getFKey($rq->getBody()->getContents());
	}

	public function sendMessage(string $message) {
		// Send message
		try {
			// try once. If rate limited then wait and try again
			$this->client->request('POST', 'https://chat.stackoverflow.com/chats/'.$this->roomID.'/messages/new', [
				'form_params' => [
					'text' => $message,
					'fkey' => $this->chatFKey
				]
			]);
		} catch (RequestException $e) {
			$ex_contents = $e->getResponse()->getBody()->getContents();
			$sleepTime = (int) filter_var($ex_contents, FILTER_SANITIZE_NUMBER_INT);
			sleep($sleepTime);
			// retry
			$this->client->request('POST', 'https://chat.stackoverflow.com/chats/'.$this->roomID.'/messages/new', [
				'form_params' => [
					'text' => $message,
					'fkey' => $this->chatFKey
				]
			]);
		}
	}

	private function getFKey(string $html) {
		libxml_use_internal_errors(true);
		$doc = new DOMDocument();
		$doc->loadHTML($html);
		libxml_use_internal_errors(false);

		$xpath = new DomXpath($doc);
		foreach ($xpath->query('//input[@name="fkey"]') as $link) {
			return $link->getAttribute('value');
		}
	}
}
