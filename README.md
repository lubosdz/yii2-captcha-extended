Yii2 Captcha Extended
=====================

[Yii2 Captcha Extended](https://github.com/lubosdz/yii2-captcha-extended) is an extension written for Yii2 framework.
It enhances original captcha code delivered along with the framework - see [DEMO](http://yii-demo.synet.sk/site/captchaExtended).
Version for Yii 1.x is available at [Yii Framework Extensions](https://www.yiiframework.com/extension/captcha-extended).

Features
========

* supports modes: default, math, mathverbal, words, logical
* supports extended characters latin1, latin2 (utf-8) including middle- east- european and cyrillyc characters
* adds visual obfuscation elements: dots density, through lines, fillSections, random text & background color
* mode-dependent options: randomUpperLowerCase (default mode), resultMultiplier (math mode)

![Screenshot](http://static.synet.sk/captchaExtendedShot.png)
![Screenshot](http://static.synet.sk/captchaExtendedShot-sk.png)
![Screenshot](http://static.synet.sk/captchaExtendedShot-de.png)

INSTALLATION
============

1) Install via composer:

```bash
$ composer require "lubosdz/yii2-captcha-extended" : "~1.0.0"
```

or you can include the following in your composer.json file:

```json
{
	"require": {
		"lubosdz/yii2-captcha-extended" : "~1.0.0"
	}
}
```

Alternatively, you may also download as ZIP archive and copy files into any directory inside your application e.g. `/app/lib/`.
Then register namespace and paths in a `classMap` in `/config/main.php`:

```php

Yii::$classMap += [
	'lubosdz\captchaExtended\CaptchaExtendedAction' => '@app/lib/yii2-captcha-extended/CaptchaExtendedAction.php',
	'lubosdz\captchaExtended\CaptchaExtendedValidator' => '@app/lib/yii2-captcha-extended/CaptchaExtendedValidator.php',
];

```

2) Define action in controller, e.g. `SiteController`:

```php

public function actions()
{
	return [
		'captcha' => [
			'class' => 'lubosdz\captchaExtended\CaptchaExtendedAction',
			// optionally, set mode and obfuscation properties:
			'mode' => 'math',
			'resultMultiplier' => 5,
			'lines' => 5,
			'height' => 50,
			'width' => 150,
		],
	];
}

```

3) Define client validation in `Model::rules()`:

```php

public function rules()
{
	return [
		['verifyCode', 'lubosdz\captchaExtended\CaptchaExtendedValidator',
			'captchaAction' => Url::to('/site/captcha'),
			'message' => 'Try again ...',
			'caseSensitive' => false
		],
	];
}

```

4) If needed, collect localized strings via CLI command `yiic message messages/config.php` and translate captcha related strings.

5) If needed, you can tune captcha modes and visibility options:

	* In "words" mode, you can place your own file [words.txt] or [words.yourlanguage.txt]
	* If needed, you can ..
		* set the dots density [0-100],
		* the number of through lines [0-]
		* the number of fillSections [0-],
		* font and background colors

6) Test & enjoy!
