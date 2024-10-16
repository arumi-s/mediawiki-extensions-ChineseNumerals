<?php

class ChineseNumeralsHooks
{

	/**
	 * @var string Chinese character for decimal point
	 */
	private static $point = '点';

	/**
	 * @var string Chinese character for decimal point
	 */
	private static $numa = [
		'零',
		'一',
		'二',
		'三',
		'四',
		'五',
		'六',
		'七',
		'八',
		'九'
	];

	/**
	 * @var array[string] Chinese characters for 0, 10, 100, 1000
	 */
	private static $numb = [
		'',
		'十',
		'百',
		'千',
	];

	/**
	 * @var array[string] Chinese characters for every 5th power of 10 (e.g. 10000, 100000000)
	 */
	private static $numc = [
		'',
		'万',
		'亿',
		'兆',
		'京',
		'垓',
		'秭',
		'穰',
		'溝',
		'澗'
	];

	/**
	 * @var array[string]string Extra convertions
	 */
	private static $pretr = [
		'两' => '二',
		'〇' => '零',
		'廿' => '二十'
	];

	/**
	 * Hook to load our parser function.
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit(Parser $parser)
	{
		$parser->setFunctionHook('cnrconvert', [__CLASS__, 'convert']);
		$parser->setFunctionHook('cnrrecover', [__CLASS__, 'recover']);
	}

	private static function toList($str = '', $def = [])
	{
		$str = trim($str);
		if ($str === '') {
			return $def;
		}

		$str = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($def as $k => &$v) {
			if (!isset($str[$k]) || $str[$k] == '-') {
				continue;
			}
			if ($str[$k] == '_') {
				$v = '';
			} else {
				$v = $str[$k];
			}
		}
		return $def;
	}

	private static function toRegex($numa, $numb, $numc, $point)
	{
		return '/([' . implode('', $numa) . implode('', $numb) . implode('', $numc) . ']+)' . $point . '?([' . implode('', $numa) . ']*)/u';
	}

	/**
	 * @param Parser $parser
	 * @param string $value
	 * @param string $stra
	 * @param string $strb
	 * @param string $strc
	 * @return string
	 */
	public static function convert($parser, $value = '', $stra = '', $strb = '', $strc = '')
	{
		if ($value == '' || !preg_match('/(\d+)\.?(\d*)/', $value, $nums)) {
			return '';
		}
		$numa = self::toList($stra, self::$numa);
		$numb = self::toList($strb, self::$numb);
		$numc = self::toList($strc, self::$numc);
		$point = self::$point;
		if ($nums[1] == 0) {
			return $numa[0] . (!empty($nums[2]) ? $point . strtr($nums[2], $numa) : '');
		}
		$u = str_split(strrev($nums[1]), 4);
		foreach ($u as $e => &$d) {
			$c = [];
			$p = '';
			for ($i = 0, $l = strlen($d); $i < $l; $p = $d[$i++]) {
				if ($d[$i] || ($i && $p != '0')) {
					$c[] = $numa[$d[$i]] . ($d[$i] ? $numb[$i] : '') . ($i == 0 && $p == '0' ? $numa[0] : '');
				}
				$s = $d[$i] == 1 && $i == 1;
			}
			$d = implode('', array_reverse($c));
			if ($d !== '') {
				$d .= $numc[$e];
			}
		}
		$l = '';
		if (!empty($nums[2])) {
			$l = $point . strtr($nums[2], $numa);
		}
		if ($s) {
			return mb_substr(implode('', array_reverse($u)), 1) . $l;
		}
		return implode('', array_reverse($u)) . $l;
	}

	/**
	 * @param Parser $parser
	 * @param string $value
	 * @param string $stra
	 * @param string $strb
	 * @param string $strc
	 * @return string
	 */
	public static function recover($parser, $value = '', $stra = '', $strb = '', $strc = '')
	{
		if ($value == '') {
			return '';
		}
		$value = strtr($value, self::$pretr);

		$numa = self::toList($stra, self::$numa);
		$numb = self::toList($strb, self::$numb);
		$numc = self::toList($strc, self::$numc);
		$point = self::$point;
		if (!preg_match(self::toRegex($numa, $numb, $numc, $point), $value, $nums)) {
			return '';
		}

		$u = preg_split('//u', $nums[1], -1, PREG_SPLIT_NO_EMPTY);
		$res = '';
		$o = [];
		$a = $d = $c = $n = 0;
		$z = true;
		do {
			$g = array_shift($u);
			$t = $g ? array_search($g, $numc) : 0;
			if (!$g || $t !== false) {
				if ($z) {
					$o[$t] = $n + $a;
				} elseif ($d < 1) {
					$o[$c - 1] = (isset($o[$c - 1]) ? $o[$c - 1] : 0) + $a * 1000;
				} else {
					$o[$t] = $n + $a * pow(10, $d - 1);
				}

				$n = $d = 0;
				$a = false;
				$c = $t;
				continue;
			}
			$t = array_search($g, $numa);
			if ($t !== false) {
				$z = ($d == 1 || $a === 0);
				$a = $t;
				continue;
			}
			$t = array_search($g, $numb);
			if ($t !== false) {
				$n += max($a, 1) * pow(10, $d = $t);
				$a = false;
				continue;
			}
		} while ($g);

		for ($i = 0, $l = max(array_keys($o)); $i <= $l; ++$i) {
			if (isset($o[$i])) {
				$res = str_pad($o[$i], 4, '0', STR_PAD_LEFT) . $res;
			} else {
				$res = '0000' . $res;
			}
		}
		$res = ltrim($res, '0');
		if ($res == '') {
			$res = '0';
		}
		if (!empty($nums[2])) {
			$res .= '.' . rtrim(strtr($nums[2], array_flip(self::$numa)), '0');
		}
		return $res;
	}
}
