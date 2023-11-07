<?php
/**
* Yii2 Captcha Extended - supports configurable obfuscation level
*
* Project:
* https://github.com/lubosdz/captcha-extended
*/

namespace lubosdz\captchaExtended;

use Yii;
use yii\web\HttpException;
use yii\web\Response;
use yii\helpers\Url;

class CaptchaExtendedAction extends \yii\captcha\CaptchaAction
{
	// Available captcha modes
	const
		// standard framework's behaviour - random latin1 characters
		MODE_DEFAULT = 'default',

		// mathematical formula as numbers, e.g. "93 - 3 = "
		MODE_MATH = 'math',

		// mathematical formula as numbers with numeric word, e.g. "How much is 12 plus 8 ?"
		MODE_MATHVERBAL	= 'mathverbal',

		// logical formula e.g. min(2, three)
		MODE_LOGICAL = 'logical',

		// random localized words with UTF-8 support according to application language
		MODE_WORDS = 'words';

	/**
	 * @var integer padding around the text. Defaults to 2.
	 */
	public $offset = 2;

	/**
	* @var string Captcha mode
	*/
	public $mode = self::MODE_DEFAULT;

	/**
	* @var int Multiplier 1 - 100 of math results to adjust difficulty, defaults to 10 - meaning math result would be 10, 20, ..
	*/
	public $resultMultiplier = 10;

	/**
	* @var bool In default mode this will randomly mix up uppercased & lowercased characters
	* When TRUE, it is recommended to set validation as case insensitive.
	*/
	public $randomUpperLowerCase = false;

	/**
	* @var string Path to the file to be used for generating random words in "words" mode
	*/
	public $fileWords;

	/**
	* @var integer Dots density around characters 0 - 100 [%], defaults 5
	*/
	public $density = 5;

	/**
	* @vat integer The number of lines drawn through the generated captcha picture, default 3
	*/
	public $lines = 3;

	/**
	* @var integer The number of sections to be filled with random flood color, default 10.
	*/
	public $fillSections = 10;

	/**
	* Customize character sets - vowels and consonants
	* You may define your own sets of characters including accented characters, numbers etc.
	* Ambiguous (mistakenly exchangable) characters are by default excluded (i-l, p-q)
	*/
	public $letters = 'bcdfghjkmnprstvwxyz';
	public $vowels = 'aeou';

	/**
	* URL parameter to enforce refreshing the newly generated captcha image
	* Framework uses by default "v" (version) by default
	* In some use cases we may want to use different param e.g. for better tracking where are robots clicking
	* @var string e.g. "v" or ID of controller where is action handled e.g. "site" or "cpanelLogin"
	*/
	public $paramRefreshUrl = 'v';

	/**
	* Init - check requirements
	*/
	public function init()
	{
		if(!extension_loaded('mbstring')){
			throw new HttpException(500, Yii::t('app', 'Missing PHP extension "{name}".', ['name' => 'mbstring']));
		}

		if(false !== stripos($this->fontFile, 'SpicyRice')){
			// replace supplied framework font (320 glyphs) with extended one (incl. latin2, cyrillic, .. 730 glyphs)
			$this->fontFile = __DIR__.'/fonts/nimbus.ttf';
		}

		// ensure minimum image size with proper width / height ratio
		$this->height = max(30, intval($this->height));

		switch ($this->mode){
			case self::MODE_WORDS:
			case self::MODE_LOGICAL:
			case self::MODE_MATHVERBAL:
				$minWidth = 300;
				break;
			case self::MODE_MATH:
			case self::MODE_DEFAULT:
			default:
				$minWidth = 150;
		}

		$this->width = max($minWidth, intval($this->width));
		$this->mode = strtolower($this->mode);

		parent::init();
	}

	/**
	* Run action
	*/
	public function run()
	{
		if(isset($_GET[self::REFRESH_GET_VAR])){
			$result = $this->getVerifyResult(true); // we must hash the result, not the code
			Yii::$app->response->format = Response::FORMAT_JSON;
			return [
				'hash1' => $this->generateValidationHash($result),
				'hash2' => $this->generateValidationHash(mb_convert_case($result, MB_CASE_LOWER, 'utf-8')),
				'url' => Url::to([$this->id, $this->paramRefreshUrl => uniqid()]),
			];
		}else{
			$this->setHttpHeaders();
			Yii::$app->response->format = Response::FORMAT_RAW;
			return $this->renderImage($this->getVerifyCode());
		}
	}

	/**
	 * Gets the verification code.
	 * @param boolean $regenerate whether the verification code should be regenerated.
	 * @return string the verification code.
	 */
	public function getVerifyCode($regenerate = false)
	{
		if($this->fixedVerifyCode !== null){
			return $this->fixedVerifyCode;
		}

		$session = Yii::$app->getSession();
		$session->open();
		$name = $this->getSessionKey();

		if(empty($session[$name.'result']) || $regenerate){
			$codeResult = $this->generateVerifyCode();
			$session[$name] = $codeResult['code'];
			$session[$name.'count'] = 1;
			$session[$name.'result'] = preg_replace('/\s+/u', '', $codeResult['result']); // white-space ignorant
		}

		return $session[$name];
	}

	/**
	* Return verification result which may differ from verification code
	* @param bool $regenerate
	* @return string the verification result.
	*/
	public function getVerifyResult($regenerate = false)
	{
		if($this->fixedVerifyCode !== null){
			return (string) $this->fixedVerifyCode;
		}

		$this->getVerifyCode($regenerate);
		$name = $this->getSessionKey();

		return (string) Yii::$app->session[$name.'result'];
	}

	/**
	 * @inheritdoc
	 */
	public function generateValidationHash($result)
	{
		$result = preg_replace('/\s+/u', '', $result);
		$result = urlencode($result); // convert accented characters to ASCII

		for ($h = 0, $i = strlen($result) - 1; $i >= 0; --$i) {
			$h += ord($result[$i]);
		}

		return $h;
	}

	/**
	 * Validates the input to see if it matches the generated code.
	 * @param string $input user input
	 * @param boolean $caseSensitive whether the comparison should be case-sensitive
	 * @return whether the input is valid
	 */
	public function validate($input, $caseSensitive){
		// open session, if necessary generate new code
		$this->getVerifyCode();

		// read result
		$session = Yii::$app->getSession();
		$key = $this->getSessionKey();
		$result = $session[$key.'result'];

		// input always without whitespaces
		$input = preg_replace('/\s+/u', '', (string) $input);
		$valid = $caseSensitive ? strcmp($input, $result) === 0 : strcasecmp($input, $result) === 0;

		// increase attempts counter, but not in case of ajax-client validation (that is always POST request having variable 'ajax')
		// otherwise captcha would be silently invalidated after entering the number of fields equaling to testlimit number
		if(empty($_POST['ajax'])){
			$name = $key.'count';
			$session[$name] = intval($session[$name]) + 1;
			if($valid || $this->testLimit > 0 && $session[$name] > $this->testLimit){
				$this->getVerifyCode(true);
			}
		}

		return $valid;
	}

	/**
	 * Generates a new verification code.
	 * @return string the generated verification code
	 */
	protected function generateVerifyCode()
	{
		switch ($this->mode){
			case self::MODE_WORDS:
				return $this->getCodeWords();
			case self::MODE_MATH:
				return $this->getCodeMath();
			case self::MODE_MATHVERBAL:
				return $this->getCodeMathVerbal();
			case self::MODE_LOGICAL:
				return $this->getCodeLogical();
			case self::MODE_DEFAULT:
			default:
				$code = $this->generateVerifyCodeUtf8();
				return [
					'code' => $code,
					'result' => $code
				];
		}
	}

	/**
	 * Generates a new verification code.
	 * @return string the generated verification code
	 */
	protected function generateVerifyCodeUtf8()
	{
		if(!$this->letters){
			$this->letters = 'bcdfghjkmnprstvwxyz';
		}
		if(!$this->vowels){
			$this->vowels = 'aeou';
		}
		if ($this->minLength < 3) {
			$this->minLength = 3;
		}
		if ($this->maxLength > 20) {
			$this->maxLength = 20;
		}
		if ($this->minLength > $this->maxLength) {
			$this->maxLength = $this->minLength;
		}
		$length = mt_rand($this->minLength, $this->maxLength);

		$letters = $this->letters;
		$vowels = $this->vowels;
		$cntVow = mb_strlen($this->vowels, 'utf-8') - 1;
		$cntLet = mb_strlen($this->letters, 'utf-8') - 1;
		$code = '';

		for ($i = 0; $i < $length; ++$i) {
			if ($i % 2 && mt_rand(0, 10) > 2 || !($i % 2) && mt_rand(0, 10) > 9) {
				$chr = mb_substr($vowels, mt_rand(0, $cntVow), 1, 'utf-8');
			} else {
				$chr = mb_substr($letters, mt_rand(0, $cntLet), 1, 'utf-8');
			}
			if($this->randomUpperLowerCase){
				$chr = mb_convert_case($chr, mt_rand(0, 1) ? MB_CASE_UPPER : MB_CASE_LOWER, 'utf-8');
			}
			$code .= $chr;
		}

		return $code;
	}

	/**
	* Return code for random words from text file.
	* First try to load file for current app language e.g. "words.de.txt"
	* If not found, we try to load generic fallback file without specified language e.g. "words.txt"
	*/
	protected function getCodeWords()
	{
		if($this->fileWords === null){
			// attempt to load file according to current application language e.g. [words.de.txt] for german language
			$this->fileWords = __DIR__.'/words.'.Yii::$app->language.'.txt';
			if(!is_file($this->fileWords)){
				// load fallback file - without language specification
				YII_DEBUG ? Yii::warning('Failed loading localized file from ['.$this->fileWords.'].', 'captcha.extended') : '';
				$this->fileWords = __DIR__.'/words.txt';
			}
		}

		if(!file_exists($this->fileWords)){
			throw new HttpException(500, Yii::t('app', 'File not found in "{path}"', ['path' => $this->fileWords]));
		}

		$words = file_get_contents($this->fileWords);
		$words = preg_split('/\s+/u', $words);
		$found = [];

		for($i = 0; $i < count($words); ++$i){
			// select random word
			$w = array_splice($words, mt_rand(0, count($words)), 1);
			if(!isset($w[0])){
				continue;
			}
			$w = $this->purifyWord($w[0]);
			if(mb_strlen($w, 'utf-8') > 3){
				$found[] = $w;
				if(mb_strlen(implode('', $found), 'utf-8') >= $this->minLength){
					break;
				}
			}
		}

		$code = implode('  ', $found);

		return [
			'code' => $code,
			'result' => $code
		];
	}

	/**
	* Return code for math mode like 9+1= or 95-5=
	* For the sake of user's comfort formula result is always multiplication of 10.
	*/
	protected function getCodeMath()
	{
		$this->resultMultiplier = intval($this->resultMultiplier);

		if($this->resultMultiplier < 1){
			$this->resultMultiplier = 1;
		}elseif($this->resultMultiplier > 100){
			$this->resultMultiplier = 100;
		}

		$n2 = mt_rand(1, $this->resultMultiplier);

		if(mt_rand(0, 1)){
			// minus formula
			$n1 = mt_rand(1, 9) * $this->resultMultiplier + $n2;
			$code = $n1 .'-'. $n2 .'=';
			$result = $n1 - $n2;
		}else{
			// plus formula
			$n1 = mt_rand(1, 10) * $this->resultMultiplier - $n2;
			$code = $n2 .'+'. $n1 .'=';
			$result = $n2 + $n1;
		}

		return [
			'code' => $code,
			'result' => $result
		];
	}

	/**
	* Return code for verbal math mode like "How much is 1 plus 1 ?"
	*/
	protected function getCodeMathVerbal()
	{
		$n2 = mt_rand(1,9);

		if(mt_rand(1,100) > 50){
			switch(mt_rand(0, 2)){
				case 0:
					$op = Yii::t('app', 'minus');
					break;
				case 1:
					$op = Yii::t('app', 'deducted by');
					break;
				case 2:
					$op = '-';
					break;
			}
			$n1 = mt_rand(1, 9) * 10 + $n2;
			$code = $n1.' '.$op.' '. (mt_rand(1, 10) > 3 ? self::getNumber($n2) : $n2);
			$result = $n1 - $n2;
		}else{
			switch(mt_rand(0, 2)){
				case 0:
					$op = Yii::t('app', 'plus');
					break;
				case 1:
					$op = Yii::t('app', 'and');
					break;
				case 2:
					$op = '+';
					break;
			}
			$n1 = mt_rand(1, 10) * 10 - $n2;
			$code = $n1.' '.$op.' '.(mt_rand(1, 10) > 3 ? self::getNumber($n2) : $n2);
			$result = $n1 + $n2;
		}

		switch (mt_rand(0, 2)){
			case 0:
				$question = Yii::t('app', 'How much is');
				break;
			case 1:
				$question = Yii::t('app', 'Result of');
				break;
			case 2:
				$question = '';
				break;
		}

		switch (mt_rand(0, 2)){
			case 0:
				$equal = '?';
				break;
			case 1:
				$equal = '=';
				break;
			case 2:
				$equal = str_repeat('.', mt_rand(0, 3));
				break;
		}

		$code = $question.' '.$code.' '.$equal;

		return [
			'code' => $code,
			'result' => $result
		];
	}

	/**
	* Return code for logical formula like min(one,7,four)
	*/
	protected function getCodeLogical()
	{
		$t = mt_rand(2, 3); // formula length
		$a = [];

		for($i = 0; $i < $t; ++$i){
			// we dont use zero
			$a[] = mt_rand(1,9);
		}

		if(mt_rand(0, 1)){
			$result = max($a);
			$code = [];
			for($i = 0; $i < count($a); ++$i){
				$code[] = mt_rand(1, 100) > 50 ? self::getNumber($a[$i]) : $a[$i];
			}
			$code = mt_rand(0, 1) ? Yii::t('app', 'max').' ( '.implode(', ',$code).' )' : Yii::t('app', 'Greatest of').' '.implode(', ',$code);
		}else{
			$result = min($a);
			$code = [];
			for($i = 0; $i < count($a); ++$i){
				$code[] = mt_rand(1, 100) > 50 ? self::getNumber($a[$i]) : $a[$i];
			}
			$code = mt_rand(0, 1) ? Yii::t('app', 'min').' ( '.implode(', ',$code).' )' : Yii::t('app', 'Lowest of').' '.implode(', ',$code);
		}

		return [
			'code' => $code,
			'result' => $result
		];
	}

	/**
	* Return captcha word without dirty characters like *,/,{,},.. Retain diacritics if unicode supported.
	* @param string $w The word to be purified
	*/
	protected function purifyWord($w)
	{
		if(@preg_match('/\pL/u', 'a')){
			// unicode supported, we remove everything except for characters
			$w = preg_replace('/[^\p{L}]/u', '', (string) $w);
		}else{
			// Unicode is not supported. Cannot validate accented characters, we keep only latin1
			$w = preg_replace('/[^a-zA-Z0-9]/', '', $w);
		}

		return $w;
	}

	/**
	* Return verbal representation for supplied number, like 1 => one
	* @param int $n The number to be translated
	*/
	protected static function getNumber($n)
	{
		static $nums;
		if(!$nums){
			$nums = self::getNumbers();
		}
		return array_key_exists($n, $nums) ? $nums[$n] : '';
	}

	/**
	* Return numbers 0..9 translated into word
	*/
	protected static function getNumbers()
	{
		return [
			'0' => Yii::t('app', 'zero'),
			'1' => Yii::t('app', 'one'),
			'2' => Yii::t('app', 'two'),
			'3' => Yii::t('app', 'three'),
			'4' => Yii::t('app', 'four'),
			'5' => Yii::t('app', 'five'),
			'6' => Yii::t('app', 'six'),
			'7' => Yii::t('app', 'seven'),
			'8' => Yii::t('app', 'eight'),
			'9' => Yii::t('app', 'nine'),
		];
	}

	/**
	 * Renders the CAPTCHA image based on the code via GD library
	 * @param string $code the verification code
	 * @return string image contents in PNG format.
	 */
	protected function renderImageByGD($code)
	{
		if(!$code){
			throw new HttpException(500, Yii::t('app', 'Verification code may not be empty.'));
		}

		$image = imagecreatetruecolor($this->width, $this->height);

		$backColor = imagecolorallocate(
			$image,
			(int)($this->backColor % 0x1000000 / 0x10000),
			(int)($this->backColor % 0x10000 / 0x100),
			$this->backColor % 0x100
		);
		imagefilledrectangle($image, 0, 0, $this->width, $this->height, $backColor);
		imagecolordeallocate($image, $backColor);

		if($this->transparent){
			imagecolortransparent($image, $backColor);
		}

		$length = mb_strlen($code, 'utf-8');
		$box = imagettfbbox(30, 0, $this->fontFile, $code);
		$w = $box[4] - $box[0] + $this->offset * ($length - 1);
		$h = $box[1] - $box[5];
		$scale = min(($this->width - $this->padding * 2) / ($w ? $w : 1), ($this->height - $this->padding * 2) / ($h ? $h : 1));
		$x = max(10, intval(($this->width - $w - $this->padding * 2) / 2.5)); // center formula
		$y = round($this->height * mt_rand(70, 90) / 100);

		$r = (int)($this->foreColor % 0x1000000 / 0x10000);
		$g = (int)($this->foreColor % 0x10000 / 0x100);
		$b = $this->foreColor % 0x100;

		// default font color
		$foreColor = imagecolorallocate(
			$image,
			mt_rand(max(0, $r - 50), min(255, $r + 50)),
			mt_rand(max(0, $g - 50), min(255, $g + 50)),
			mt_rand(max(0, $b - 50), min(255, $b + 50))
		);

		for($i = 0; $i < $length; ++$i){
			$fontSize = (int)(rand(22, 33) * $scale * 0.85);
			$angle = rand(-10, 10);
			$letter = mb_substr($code, $i, 1, 'utf-8');

			// randomize font color
			if(mt_rand(0, 10) > 7){
				$foreColor = imagecolorallocate(
					$image,
					mt_rand(max(0, $r - 50), min(255, $r + 50)),
					mt_rand(max(0, $g - 50), min(255, $g + 50)),
					mt_rand(max(0, $b - 50), min(255, $b + 50))
				);
			}

			$box = imagettftext($image, $fontSize, $angle, $x, $y, $foreColor, $this->fontFile, $letter);
			$x = $box[2] + $this->offset;
		}

		// add density dots
		$this->density = intval($this->density);
		if($this->density > 0){
			$length = intval($this->width * $this->height / 100 * $this->density);
			$c = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
			for($i = 0; $i < $length; ++$i){
				$x = mt_rand(0, $this->width);
				$y = mt_rand(0, $this->height);
				imagesetpixel($image, $x, $y, $c);
			}
		}

		// add lines
		$this->lines = intval($this->lines);
		if($this->lines > 0){
			for($i = 0; $i < $this->lines; ++$i){
				imagesetthickness($image, mt_rand(1, 2));
				$c = imagecolorallocate($image, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
				$x = mt_rand(0, $this->width);
				$y = mt_rand(0, $this->width);
				imageline($image, $x, 0, $y, $this->height, $c);
			}
		}

		// add flood sections
		$this->fillSections = intval($this->fillSections);
		if($this->fillSections > 0){
			for($i = 0; $i < $this->fillSections; ++$i){
				$c = imagecolorallocate($image, mt_rand(200, 255), mt_rand(200, 255), mt_rand(200, 255));
				$x = mt_rand(0, $this->width);
				$y = mt_rand(0, $this->height);
				imagefill($image, $x, $y, $c);
			}
		}

		imagecolordeallocate($image, $foreColor);

		ob_start();
		imagepng($image);
		imagedestroy($image);
		return ob_get_clean();
	}

	/**
	 * Renders the CAPTCHA image based on the code using ImageMagick library.
	 * @param string $code the verification code
	 * @return string image contents in PNG format.
	 */
	protected function renderImageByImagick($code)
	{
		if(!$code){
			throw new HttpException(500, Yii::t('app', 'Verification code may not be empty.'));
		}

		$backColor = $this->transparent ? new \ImagickPixel('transparent') : new \ImagickPixel('#' . str_pad(dechex($this->backColor), 6, 0, STR_PAD_LEFT));

		$r = (int)($this->foreColor % 0x1000000 / 0x10000);
		$g = (int)($this->foreColor % 0x10000 / 0x100);
		$b = $this->foreColor % 0x100;
		$hex = str_pad(dechex(mt_rand(max(0, $r - 50), min(255, $r + 50))), 2, 0, STR_PAD_LEFT)
			  .str_pad(dechex(mt_rand(max(0, $g - 50), min(255, $g + 50))), 2, 0, STR_PAD_LEFT)
			  .str_pad(dechex(mt_rand(max(0, $b - 50), min(255, $b + 50))), 2, 0, STR_PAD_LEFT);
		$foreColor = new \ImagickPixel("#{$hex}");

		$image = new \Imagick();
		$image->newImage($this->width, $this->height, $backColor);

		$draw = new \ImagickDraw();
		$draw->setFont($this->fontFile);
		$draw->setFontSize(30);
		$fontMetrics = $image->queryFontMetrics($draw, $code);

		$length = mb_strlen($code, 'utf-8');
		$w = (int) $fontMetrics['textWidth'] - 8 + $this->offset * ($length - 1);
		$h = (int) $fontMetrics['textHeight'] - 8;
		$scale = min(($this->width - $this->padding * 2) / $w, ($this->height - $this->padding * 2) / $h);
		$x = max(10, intval(($this->width - $w - $this->padding * 2) / 2.5)); // center formula
		$y = round($this->height * mt_rand(70, 90) / 100);
		for ($i = 0; $i < $length; ++$i) {
			// randomize font color
			if(mt_rand(0, 10) > 7){
				$hex = str_pad(dechex(mt_rand(max(0, $r - 50), min(255, $r + 50))), 2, 0, STR_PAD_LEFT)
					  .str_pad(dechex(mt_rand(max(0, $g - 50), min(255, $g + 50))), 2, 0, STR_PAD_LEFT)
					  .str_pad(dechex(mt_rand(max(0, $b - 50), min(255, $b + 50))), 2, 0, STR_PAD_LEFT);
				$foreColor = new \ImagickPixel("#{$hex}");
			}
			$draw = new \ImagickDraw();
			$draw->setFont($this->fontFile);
			$draw->setFontSize((int) (mt_rand(22, 33) * $scale * 0.85));
			$draw->setFillColor($foreColor);
			$letter = mb_substr($code, $i, 1, 'utf-8');
			$image->annotateImage($draw, $x, $y, mt_rand(-10, 10), $letter);
			$fontMetrics = $image->queryFontMetrics($draw, $letter);
			$x += (int) $fontMetrics['textWidth'] + $this->offset;
		}

		// add density dots
		$this->density = intval($this->density);
		if($this->density > 0){
			$length = intval($this->width * $this->height / 100 * $this->density);
			$hex = str_pad(dechex(mt_rand(200, 255)), 2, 0, STR_PAD_LEFT)
				  .str_pad(dechex(mt_rand(200, 255)), 2, 0, STR_PAD_LEFT)
				  .str_pad(dechex(mt_rand(200, 255)), 2, 0, STR_PAD_LEFT);
			$foreColor = new \ImagickPixel("#{$hex}");
			$draw = new \ImagickDraw();
			for($i = 0; $i < $length; ++$i){
				$x = mt_rand(0, $this->width);
				$y = mt_rand(0, $this->height);
				$draw->point($x, $y);
			}
			$image->drawImage($draw);
		}

		// add lines
		$this->lines = intval($this->lines);
		if($this->lines > 0){
			$draw = new \ImagickDraw();
			for($i = 0; $i < $this->lines; ++$i){
				$hex = str_pad(dechex(mt_rand(200, 255)), 2, 0, STR_PAD_LEFT)
					  .str_pad(dechex(mt_rand(200, 255)), 2, 0, STR_PAD_LEFT)
					  .str_pad(dechex(mt_rand(200, 255)), 2, 0, STR_PAD_LEFT);
				$foreColor = new \ImagickPixel("#{$hex}");
				$draw->setStrokeColor($foreColor);
				$draw->setStrokeWidth(mt_rand(1, 2));
				$x = mt_rand(0, $this->width);
				$y = mt_rand(0, $this->width);
				$draw->line($x, 0, $y, $this->height);
			}
			$image->drawImage($draw);
		}

		// add flood sections
		$this->fillSections = intval($this->fillSections);
		if($this->fillSections > 0){
			$draw = new \ImagickDraw();
			for($i = 0; $i < $this->fillSections; ++$i){
				$hex = str_pad(dechex(mt_rand(200, 255)), 2, 0, STR_PAD_LEFT)
					  .str_pad(dechex(mt_rand(200, 255)), 2, 0, STR_PAD_LEFT)
					  .str_pad(dechex(mt_rand(200, 255)), 2, 0, STR_PAD_LEFT);
				$foreColor = new \ImagickPixel("#{$hex}");
				$x = mt_rand(1, $this->width - 1);
				$y = mt_rand(1, $this->height - 1);
				$target = $image->getImagePixelColor($x, $y);
				$image->floodFillPaintImage($foreColor, 1, $target, $x, $y, false);
			}
		}

		$image->setImageFormat('png');
		return $image->getImageBlob();
	}

}
