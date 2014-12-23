<?php

class Feediron_Json{
	private static $json_error;
	public static function format($json){
		$result      = '';
		$pos         = 0;
		$strLen      = strlen($json);
		$indentStr   = '    ';
		$newLine     = "\n";
		$prevChar    = '';
		$outOfQuotes = true;
		$currentline = 0;
		$possible_errors = array (
			',]' => 'Additional comma before ] (%s)',
			'""' => 'Missing seperator between after " (%s)',
			',}' => 'Additional comma before } (%s)',
			',:' => 'Comma before :(%s)',
			']:' => '] before :(%s)',
			'}:' => '} before :(%s)',
			'[:' => '[ before :(%s)',
			'{:' => '{ before :(%s)',
		);

		for ($i=0; $i<=$strLen; $i++) {

			// Grab the next character in the string.
			$char = substr($json, $i, 1);
			if($char == $newLine){
				$currentline++;
				continue;
			}
			if($char == ' ' && $outOfQuotes){
				continue;
			}

			if (array_key_exists($prevChar.$char, $possible_errors)){
				self::$json_error = sprintf($possible_errors[$prevChar.$char]. ' in line %s', substr($result, self::strrpos_count($result,$newLine,3)), $currentline);
			}
			// Are we inside a quoted string?
			if ($char == '"' && $prevChar != '\\') {
				$outOfQuotes = !$outOfQuotes;

				// If this character is the end of an element,
				// output a new line and indent the next line.
			} else if(($char == '}' || $char == ']') && $outOfQuotes) {
				$result .= $newLine;
				$pos --;
				for ($j=0; $j<$pos; $j++) {
					$result .= $indentStr;
				}
			}

			// Add the character to the result string.
			$result .= $char;

			// If the last character was the beginning of an element,
			// output a new line and indent the next line.
			if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
				$result .= $newLine;
				if ($char == '{' || $char == '[') {
					$pos ++;
				}

				for ($j = 0; $j < $pos; $j++) {
					$result .= $indentStr;
				}
			}
			else if($char == ':' && $outOfQuotes){
				$result .= ' ';
			}

			$prevChar = $char;
		}

		return $result;
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
