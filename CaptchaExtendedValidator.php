<?php
/**
* Yii2 Captcha Extended - client validator
*
* Project:
* https://github.com/lubosdz/captcha-extended
*/

namespace lubosdz\captchaExtended;

use Yii;
use yii\validators\ValidationAsset;

class CaptchaExtendedValidator extends \yii\captcha\CaptchaValidator
{
	public function clientValidateAttribute($object, $attribute, $view){

		/** @var \yii\web\View */
		$view;

		$captcha = $this->createCaptchaAction();
		$result = $captcha->getVerifyResult();

		if(!$this->caseSensitive){
			$result = mb_convert_case($result, MB_CASE_LOWER, 'utf-8');
		}

		$hash = $captcha->generateValidationHash($result);

		$options = [
			'hash' => $hash,
			'hashKey' => 'yiiCaptcha/' . $this->captchaAction,
			'caseSensitive' => $this->caseSensitive,
			'message' => Yii::$app->getI18n()->format($this->message, [
				'attribute' => $object->getAttributeLabel($attribute),
			], Yii::$app->language),
		];

		if ($this->skipOnEmpty) {
			$options['skipOnEmpty'] = 1;
		}

		ValidationAsset::register($view);

		// override default captcha validator in assets "yii.validation.js"
		$js = <<<JS
yii.validation = yii.validation || {};
yii.validation = $.extend(yii.validation, {

	captcha : function (value, messages, options) {
		if (options.skipOnEmpty && this.isEmpty(value)) {
			return;
		}
		if(options && options.hashKey != undefined){
			options.hashKey = options.hashKey.replace('//', '/'); // fix double slash in URLs
		}

		value = value.replace(/\s+/g, '');

		// CAPTCHA may be updated via AJAX and the updated hash is stored in body data
		var hash = $('body').data(options.hashKey);
		if (hash == null) {
			hash = options.hash;
		} else {
			hash = hash[options.caseSensitive ? 0 : 1];
		}
		var v = options.caseSensitive ? value : value.toLowerCase();
		v = encodeURIComponent(v);

		for (var i = v.length - 1, h = 0; i >= 0; --i) {
			h += v.charCodeAt(i);
		}

		if (h != hash) {
			this.addMessage(messages, options.message, value);
		}
	}
});
JS;
$view->registerJs($js);

		return 'yii.validation.captcha(value, messages, ' . json_encode($options, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ');';
	}
}
