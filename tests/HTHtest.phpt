<?php

/**
 * Not a real test!
 */

$bodyMarkdown = <<<'TEXT'
"There&#39;s no difference between them in a final class, so use whichever you want. \r\n\r\nBoth calls will return `A`.\r\n```\r\n&lt;?php\r\n\r\nclass Dad {\r\n    static function getStatic() {\r\n        return new static;\r\n    }\r\n    \r\n    static function getSelf() {\r\n        return new self;\r\n    }\r\n}\r\n\r\ntrait Useless {\r\n    static function getStatic() {\r\n        return new static;\r\n    }\r\n}\r\n\r\nfinal class A extends Dad {\r\n    use Useless;\r\n    \r\n    static function getSelf() {\r\n        return new self;\r\n    }\r\n}\r\n\r\nvar_dump(A::getStatic()::class);\r\nvar_dump(A::getSelf()::class);\r\n```\r\n\r\n\r\n\r\n[Example][1]\r\n\r\nhope this helps\r\ndharman\r\n\r\n  [1]: https://3v4l.org/CsmAr#v8.0.8"
TEXT;

$bodyCleansed = json_decode($bodyMarkdown);

$username = preg_quote('Dharman', '/');
$re = '/(*ANYCRLF)          # $ matches both \r and \n
((?<=\.)|\s*^)\s*			#space before
(I\h)?hope\h(it|this|that)
(\hwill\b|\hcan\b)?
\hhelps?
(\h(you|someone(?:\h*else)?)\b)?
(:-?\)|🙂️|[!.;,\s])*?				#punctuation and emoji
(\s*(cheers|good ?luck)([!,.]*))?	# sometimes appears on the same line or next
(?:[-~\s]*'.$username.')?
$/mix';
$bodyCleansed = preg_replace($re, '', $bodyCleansed, -1, $count);

$expected = <<<'EXPECTED'
"There&#39;s no difference between them in a final class, so use whichever you want. \r\n\r\nBoth calls will return `A`.\r\n```\r\n&lt;?php\r\n\r\nclass Dad {\r\n    static function getStatic() {\r\n        return new static;\r\n    }\r\n    \r\n    static function getSelf() {\r\n        return new self;\r\n    }\r\n}\r\n\r\ntrait Useless {\r\n    static function getStatic() {\r\n        return new static;\r\n    }\r\n}\r\n\r\nfinal class A extends Dad {\r\n    use Useless;\r\n    \r\n    static function getSelf() {\r\n        return new self;\r\n    }\r\n}\r\n\r\nvar_dump(A::getStatic()::class);\r\nvar_dump(A::getSelf()::class);\r\n```\r\n\r\n\r\n\r\n[Example][1]\r\n\r\n  [1]: https:\/\/3v4l.org\/CsmAr#v8.0.8"
EXPECTED;

assert($expected === json_encode($bodyCleansed));
echo 'PASSED - Not a real test!';