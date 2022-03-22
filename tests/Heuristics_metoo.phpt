<?php

use Entities\Post;

define('BASE_DIR', realpath(__DIR__.'/..'));
include BASE_DIR.'/vendor/autoload.php';


// false case
$json = <<<'JSON'
{
    "owner": {
    "reputation": 1,
    "user_id": 14570844,
    "user_type": "unregistered",
    "profile_image": "https://www.gravatar.com/avatar/d4e403f79e47035b0351e119f50e7e13?s=128&d=identicon&r=PG",
    "display_name": "Yura",
    "link": "https://stackoverflow.com/users/14570844/yura"
    },
    "is_accepted": false,
    "score": -1,
    "creation_date": 1604409410,
    "answer_id": 64663596,
    "question_id": 61782798,
    "link": "https://stackoverflow.com/questions/61782798/gas-error-typeerror-cannot-read-property-getlastrow-of-null-line-4-file-c/64663596#64663596",
    "title": "GAS error: TypeError: Cannot read property 'getLastRow' of null (line 4, file \"Code2\")",
    "body_markdown": "I was also facing the same issue. \r\nI just created a normal text file. opened it in notepad and pasted the whole SQL script. \r\n[![enter image description here][1]][1]\r\n\r\n\r\n  [1]: https://i.stack.imgur.com/I9SF7.png\r\n\r\n\r\nthen renamed it to SQL file (ex: filename.sql)\r\nAfter that, I tried to run it through the flyway and it worked. \r\n",
    "body": "<p>I was also facing the same issue.\nI just created a normal text file. opened it in notepad and pasted the whole SQL script.\n<a href=\"https://i.stack.imgur.com/I9SF7.png\" rel=\"nofollow noreferrer\"><img src=\"https://i.stack.imgur.com/I9SF7.png\" alt=\"enter image description here\" /></a></p>\n<p>then renamed it to SQL file (ex: filename.sql)\nAfter that, I tried to run it through the flyway and it worked.</p>\n"
  }
JSON;

$json = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

$post = new Post($json);
$h = new Heuristics($post);

assert($h->MeTooAnswer() === []);


// true case
$json = <<<'JSON'
{
    "owner": {
    "reputation": 1,
    "user_id": 14570844,
    "user_type": "unregistered",
    "profile_image": "https://www.gravatar.com/avatar/d4e403f79e47035b0351e119f50e7e13?s=128&d=identicon&r=PG",
    "display_name": "Yura",
    "link": "https://stackoverflow.com/users/14570844/yura"
    },
    "is_accepted": false,
    "score": -1,
    "creation_date": 1604409410,
    "answer_id": 64663596,
    "question_id": 61782798,
    "link": "https://stackoverflow.com/questions/61782798/gas-error-typeerror-cannot-read-property-getlastrow-of-null-line-4-file-c/64663596#64663596",
    "title": "GAS error: TypeError: Cannot read property 'getLastRow' of null (line 4, file \"Code2\")",
    "body_markdown": "I was also facing the same issue. \r\nI just created a normal text file. opened it in notepad and pasted the whole SQL script. \r\n[![enter image description here][1]][1]\r\n\r\n\r\n  [1]: https://i.stack.imgur.com/I9SF7.png\r\n\r\n\r\nthen renamed it to SQL file (ex: filename.sql)\r\nAfter that, I tried to run it through the flyway and it worked. \r\n",
    "body": "<p>I also face the same issue.\nI just created a normal text file. opened it in notepad and pasted the whole SQL script.\n<a href=\"https://i.stack.imgur.com/I9SF7.png\" rel=\"nofollow noreferrer\"><img src=\"https://i.stack.imgur.com/I9SF7.png\" alt=\"enter image description here\" /></a></p>\n<p>then renamed it to SQL file (ex: filename.sql)\nAfter that, I tried to run it through the flyway and it worked.</p>\n"
  }
JSON;

$json = json_decode($json, false, 512, JSON_THROW_ON_ERROR);

$post = new Post($json);
$h = new Heuristics($post);

assert($h->MeTooAnswer() === [['Word' => 'I also face the same issue', 'Type' => 'MeTooAnswer']]);


echo 'PASSED';
