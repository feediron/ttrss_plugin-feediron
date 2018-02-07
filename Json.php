<?php

class Feediron_Json{
	private static $json_error;
	public static function format($json){
		return json_encode(json_decode($json), JSON_PRETTY_PRINT);
	}
	private static function strrpos_count($haystack, $needle, $count)
	{
		if($count <= 0)
			return false;

		$len = strlen($haystack);
		$pos = $len;

		for($i = 0; $i < $count && $pos; $i++)
			$pos = strrpos($haystack, $needle, $pos - $len - 1);

		return $pos;
	}
	public static function get_error(){
		return self::$json_error;
	}
}
