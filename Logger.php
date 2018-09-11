<?php

class Feediron_Logger{

	private static $logger = FALSE;
	const LOG_NONE = 0;
	const LOG_TTRSS = 1;
	const LOG_TEST = 2;
	const LOG_VERBOSE = 3;

	public static function get(){
		if(self::$logger === FALSE){
			self::$logger = new Feediron_Logger();
		}
		return self::$logger;
	}

	private $loglevel = self::LOG_NONE;
	private $testlog = array();
	private $time_measure = false;

	public function get_log_level(){
		return $this->loglevel;
	}
	public function set_log_level($level){
		$this->loglevel = $level;
	}

	public function log($level, $msg, $details=''){
		if($level > $this->loglevel)
			return;
		if($level == self::LOG_TTRSS){
			trigger_error($msg, E_USER_NOTICE);
			array_push($this->testlog, "<h2>LOG:</h2><pre>".htmlentities($msg)."</pre>");
		}else{
			array_push($this->testlog, "<h2>$msg</h2>");
			array_push($this->testlog, "<details><pre>".htmlentities($details)."</pre></details>");
		}
	}
	public function log_json($level, $msg, $json){
		if($level > $this->loglevel)
			return;
		$this->log($level, $msg, Feediron_Json::format($json));
	}
	public function log_object($level, $msg, $obj){
		if($level > $this->loglevel)
			return;
		$this->log_json($level, $msg, json_encode($obj));
	}
	public function log_html($level, $msg, $html){
		if($level > $this->loglevel)
			return;
		$this->log($level, $msg, $html);
	}
	public function get_testlog(){
		return $this->testlog;
	}
}
