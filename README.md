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
![Screenshot](http://static.synet.sk/captchaExtendedShot-sk.png)<br />
![Screenshot](http://static.synet.sk/captchaExtendedShot-de.png)
![Screenshot](http://static.synet.sk/captchaExtended-logical.gif)<br />
![Screenshot](http://static.synet.sk/captchaExtended-mathverbal.gif)<br />
![Screenshot](http://static.synet.sk/captchaExtended-math.gif)

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

2) Define action in controller, e.g. `SiteController`:

```php

public function actions()
{
	return [
		'captcha' => [
			'class' => 'lubosdz\captchaExtended\CaptchaExtendedAction',
			// optionally, set mode and obfuscation properties e.g.:
			'mode' => 'math',
			//'resultMultiplier' => 5,
			//'lines' => 5,
			//'height' => 50,
			//'width' => 150,
		],
	];
}

```

3) Define client validation in `LoginForm::rules()`:

```php

public $verifyCode;

public function rules()
{
	return [
		['verifyCode', 'lubosdz\captchaExtended\CaptchaExtendedValidator',
			'captchaAction' => Url::to('/site/captcha'),
		],
	];
}

```

4) In view defined captcha field inside login form e.g.:

```php

<?php $form = ActiveForm::begin() ?>

// ...

<?= $form->field($model, 'verifyCode')->widget(Captcha::className(), [
	'captchaAction' => Url::to('/site/captcha'),
	'template' => '<div class="text-center">{image}</div><br/> {input} ',
	'imageOptions' => [
		'style' => 'cursor:pointer;',
		'title' => Yii::t('app', 'Click to refresh the code'),
	],
	'options' => [
		'placeholder' => Yii::t('app', 'Verification code'),
		'class' => 'form-control',
	],
])->label(false) ?>

// ...

<?php ActiveForm::end(); ?>

```

4) If needed, collect localized strings via CLI command `yiic message messages/config.php` and translate captcha related strings.

5) Since by default capchta configures to default framework's settings, you may want to adjust some options:

	* `mode` - default|math|mathverbal|logical|words,
		* for the `words` mode, you can replace your own file [words.txt] or [words.yourlanguage.txt]
	* `density` - dots density [0-100],
	* `lines` - the number of through lines [0-20],
	* `fillSections` - the number of fillSections [0-20],
	* `letters` - define your own first characters set (UTF-8 supported)
	* `vowels` - define your own second characters set (UTF-8 supported)
	* `resultMultiplier` - applied to math mode to increase formula difficulty
	* `fileWords` - abs. path to file with your own defined locale words (UTF-8 supported)
	* `randomUpperLowerCase` - mix up randomly upper & lower characters from characters sets
	* also note standard properties supported by framework: `width`, `height`, `padding`, `offset`, `foreColor`, `backColor`, `transparent`, `minLength`, `maxLength`, ..

6) Enjoy!
