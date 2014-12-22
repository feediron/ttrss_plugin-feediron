<?php

class Feediron extends Plugin implements IHandler
{
	private $host;
	private $debug;
	private $is_test;
	private $testlog;
	private $charset;
	private $json_error;
	const plugindata_tag = 'feediron';

	// Required API
	function about()
	{
		return array(
			1.0,   // version
			'Irons a feeds content to your needs',   // description
			'm42e',   // author
			false,   // is_system
		);
	}

	// Required API
	function api_version()
	{
		return 2;
	}

	// Required API for adding the hooks
	function init($host)
	{
		$this->host = $host;
		$host->add_hook($host::HOOK_PREFS_TABS, $this);
		$host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
	}

	// Required API, Django...
	function csrf_ignore($method)
	{
		$csrf_ignored = array("index", "edit");
		return array_search($method, $csrf_ignored) !== false;
	}

	// Allow only in active sessions
	function before($method)
	{
		if ($_SESSION["uid"])
		{
			return true;
		}
		return false;
	}

	// Required API
	function after()
	{
		return true;
	}

	// Log messages to syslog
	private function _log($msg)
	{
		if ($this->debug)
		{
			trigger_error($msg, E_USER_WARNING);
		}
		if($this->is_test){
			array_push($this->testlog, "LOG:<pre>$msg</pre>");
		}
	}
	private function _testlog($title, $msg){
		if($this->is_test){
			array_push($this->testlog, "<h2>$title</h2>");
			array_push($this->testlog, "<pre>$msg</pre>");
		}
	}


	// The hook to filter the article. Called for each article
	function hook_article_filter($article)
	{
		$this->is_test = 0;
		if (($config = $this->getConfigSection($article['link'])) !== FALSE)
		{
			$articleMarker = $this->getMarker($article, $config);
			if (strpos($article['plugin_data'], $articleMarker) !== false)
			{
				return $article;
			}

			$this->_log("Article was not fetched yet: ".$article['link']);
			$link = $this->reformatUrl($article['link'], $config);
			$article['content'] = $this->getNewContent($link, $config);
			$article['plugin_data'] = $this->addArticleMarker($article, $articleMarker);
		}

		return $article;
	}
	//Creates a marker for the article processed with specific config
	function getMarker($article, $config){
		$articleMarker = self::plugindata_tag.":".$article['owner_uid'].",".md5(print_r($config, true)).":";
		return $articleMarker;
	}

	// Removes old marker and adds new one
	function addArticleMarker($article, $marker){
		return preg_replace('/'.self::plugindata_tag.':'.$article['owner_id'].',.*?:/','',$article['plugin_data']).$marker;
	}

	function getConfigSection($url)
	{
		$data = $this->getConfig();
		if(is_array($data)){
			foreach ($data as $urlpart=>$config) {
				if (strpos($url, $urlpart) === false){
					continue;   // skip this config if URL not matching
				}
				$this->_log("Config found");
				$this->_testlog("Config", $this->formatjson(json_encode($config)));
				return $config;
			}
		}
		$this->_log("No config found for ".$url);
		return FALSE;
	}

	// Load config
	function getConfig()
	{
		$json_conf = $this->host->get($this, 'json_conf');
		$data = json_decode($json_conf, true);
		if(!is_array($data)){
			$this->_log("No Config found");
		}
		$this->debug = isset($data['debug']) && $data['debug'];
		return $data;
	}

	// reformat an url with a given config
	function reformatUrl($url, $config)
	{
		$link = trim($url);
		if(is_array($config['reformat']))
		{
			$link = $this->reformat($link, $config['reformat']);
			$this->_log("Reformated url: ".$link);
		}
		return $link;
	}

	// reformat a string with given options
	function reformat($string, $options)
	{
		foreach($options as $option)
		{
			switch($option['type'])
			{
			case 'replace':
				$string = str_replace($option['search'], $option['replace'], $string);
				break;

			case 'regex':
				$string = preg_replace($option['pattern'], $option['replace'], $string);
				break;
			}
		}
		return $string;
	}

	// grep new content for a link and aplly config
	function getNewContent($link, $config)
	{
		$links = $this->getLinks($link, $config);
		$this->_log("Fetching ".count($links)." links");
		$html_complete = "";
		foreach($links as $lnk)
		{
			$html = $this->getArticleContent($lnk, $config);
			$this->_testlog("Original Source ".$lnk.":", htmlentities($html));
			$html = $this->processArticle($html, $config);
			$this->_testlog("Modified Source ".$lnk.":", htmlentities($html));
			$html_complete .= $html;
		}
		return $html_complete;

	}
	//extract links for multipage articles
	function getLinks($link, $config){
		if (isset($config['multipage']))
		{
			$links = $this->fetch_links($link, $config);
		}
		else
		{
			$links = array($link);
		}
		return $links;
	}
	function getArticleContent($link, $config)
	{
		list($html, $content_type) = $this->get_content($link);

		$this->charset = false;
		if (!isset($config['force_charset']))
		{
			if ($content_type)
			{
				preg_match('/charset=(\S+)/', $content_type, $matches);
				if (isset($matches[1]) && !empty($matches[1])) {
					$this->charset = $matches[1];
				}
			}
		} else {
			// use forced charset
			$this->charset = $config['force_charset'];
		}

	
		$this->_testlog("charset:", $this->charset);
		if ($this->charset && isset($config['force_unicode']) && $config['force_unicode'])
		{
			$html = iconv($this->charset, 'utf-8', $html);
			$this->charset = 'utf-8';
		}
		return $html;
	}
	function get_content($link)
	{
		global $fetch_last_content_type;
		$this->_log($link);
		if (version_compare(VERSION, '1.7.9', '>='))
		{
			$html = fetch_file_contents($link);
			$content_type = $fetch_last_content_type;
		}
		else
		{
			// fallback to file_get_contents()
			$html = file_get_contents($link);

			// try to fetch charset from HTTP headers
			$headers = $http_response_header;
			$content_type = false;
			foreach ($headers as $h)
			{
				if (substr(strtolower($h), 0, 13) == 'content-type:')
				{
					$content_type = substr($h, 14);
					// don't break here to find LATEST (if redirected) entry
				}
			}
		}
		return array( $html,  $content_type);
	}
	function fetch_links($link, $config)
	{
		$html = $this->getArticleContent($link, $config);
		$links = $this->extractlinks($html, $config);
		if (count($links) == 0)
		{
			return array($link);
		}
		$links = $this->fixlinks($link, $links);
		foreach ($links as $lnk)
		{
			$this->_log("link:".$lnk);
		}
		if(isset($config['multipage']['append']) && $config['multipage']['append'])
		{
			array_unshift($links, $link);
		}
		return $links;

	}
	function extractlinks($html, $config)
	{
		$doc = $this->getDOM($html);
		$links = array();

		$xpath = new DOMXPath($doc);
		$entries = $xpath->query('(//'.$config['multipage']['xpath'].')');   // find main DIV according to config


		if ($entries->length < 1){
			return array();
		}
		foreach($entries as $entry)
		{
			$links[] = $entry->getAttribute('href');
		}
		return $links;
	}
	function getDOM($html){
		$doc = new DOMDocument();
		if ($this->charset) {
			$html = '<?xml encoding="' . $this->charset . '">' . $html;
		}
		libxml_use_internal_errors(true);
		$doc->loadHTML($html);
		if(!$doc)
		{
			$this->_log("The content is not a valid xml format");
			if($this->debug)
			{
				foreach (libxml_get_errors() as $value)
				{
					$this->_log($value);
				}
			}
			return new DOMDocument();
		}
		return $doc;
	}
	function fixlinks($link, $links)
	{
		$retlinks = array();
		foreach($links as $lnk)
		{
			$retlinks[] = $this->rel2abs($lnk, $link);
		}
		return $retlinks;
	}
	function rel2abs($rel, $base)
	{
		if (parse_url($rel, PHP_URL_SCHEME) != '' || substr($rel, 0, 2) == '//') {
			return $rel;
		}
		if ($rel[0]=='#' || $rel[0]=='?') {
			return $base.$rel;
		}
		extract(parse_url($base));
		$path = preg_replace('#/[^/]*$#', '', $path);
		if ($rel[0] == '/') {
			$path = '';
		}

		/* dirty absolute URL */
		$abs = "$host$path/$rel";

		/* replace '//' or '/./' or '/foo/../' with '/' */
		$re = array('#(/\.?/)#', '#/(?!\.\.)[^/]+/\.\./#');
		for($n=1; $n>0; $abs=preg_replace($re, '/', $abs, -1, $n)) {}

		/* absolute URL is ready! */
		return $scheme.'://'.$abs;
	}
	function processArticle($html, $config)
	{
		switch ($config['type'])
		{
		case 'split':
			$html = $this->performSplit($html, $config);
			break;

		case 'xpath':
			$html = $this->performXpath($html, $config);
			break;

		default:
			$this->_log("Unrecognized option: ".$config['type']);
			continue;
		}
		if(is_array($config['modify']))
		{
			$html = $this->reformat($html, $config['modify']);
		}
		return $html;
	}

	function performSplit($html, $config){
		$orig_html = $html;
		foreach($config['steps'] as $step)
		{
			if(isset($step['after']))
			{
				$result = preg_split ($step['after'], $html);
				$html = $result[1];
			}
			if(isset($step['before']))
			{
				$result = preg_split ($step['before'], $html);
				$html = $result[0];
			}
		}
		if(strlen($html) == 0)
		{
			return $orig_html;
		}
		if(isset($config['cleanup']))
		{
			foreach($config['cleanup'] as $cleanup)
			{
				$html = preg_replace($cleanup, '', $html);
			}
		}
		return $html;
	}

	function performXpath($html, $config)
	{
		$doc = $this->getDOM($html);
		$basenode = false;
		$xpath = new DOMXPath($doc);
		$entries = $xpath->query('(//'.$config['xpath'].')');   // find main DIV according to config

		if ($entries->length > 0) {
			$basenode = $entries->item(0);
		}

		if (!$basenode) {
			return $html;
		}

		// remove nodes from cleanup configuration
		$basenode = $this->cleanupNode($xpath, $basenode, $config);
		$html = $doc->saveXML($basenode);
		return $html;
	}

	function cleanupNode($xpath, $basenode, $config)
	{
		if(($cconfig = $this->getCleanupConfig($config))!== FALSE)
		{
			foreach ($cconfig as $cleanup)
			{
				if(strpos($cleanup, "./") !== 0)
				{
					$cleanup = '//'.$cleanup;
				}
				$nodelist = $xpath->query($cleanup, $basenode);
				foreach ($nodelist as $node)
				{
					if ($node instanceof DOMAttr)
					{
						$node->ownerElement->removeAttributeNode($node);
					}
					else
					{
						$node->parentNode->removeChild($node);
					}
				}
			}
		}
		return $basenode;
	}

	function getCleanupConfig($config)
	{
		$cconfig = false;

		if (isset($config['cleanup']))
		{
			$cconfig = $config['cleanup'];
			if (!is_array($cconfig))
			{
				$cconfig = array($cconfig);
			}
		}
		return $cconfig;
	}

	function hook_prefs_tabs($args)
	{
		print '<div id="feedironConfigTab" dojoType="dijit.layout.ContentPane"
			href="backend.php?op=feediron"
			title="' . __('FeedIron') . '"></div>';
	}

	function index()
	{
		$pluginhost = PluginHost::getInstance();
		$json_conf = $pluginhost->get($this, 'json_conf');

		print "<form dojoType=\"dijit.form.Form\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
		if (this.validate()) {
			new Ajax.Request('backend.php', {
				parameters: dojo.objectToQuery(this.getValues()),
					onComplete: function(transport) {
						if (transport.responseJSON.success == false){
							notify_error(transport.responseJSON.errormessage);
							dojo.query('#json_error').attr('innerHTML', transport.responseJSON.json_error).attr('class','error');
	}
else {
	notify_info(transport.responseJSON.message);
	dojo.query('#json_conf').attr('value',transport.responseJSON.json_conf);
	dojo.query('#json_error').attr('innerHTML', '').attr('class','');
	}
	}
	});
	}
			</script>";

		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"save\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"feediron\">";

		print "<table width='100%'><tr><td>";
		print "<textarea dojoType=\"dijit.form.SimpleTextarea\" id=\"json_conf\" name=\"json_conf\" style=\"font-size: 12px; width: 99%; height: 500px;\">$json_conf</textarea>";
		print "</td></tr></table>";

		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".__("Save")."</button><div id=\"json_error\"></div></p>";

		print "</form>";
		print "<form dojoType=\"dijit.form.Form\">";

		print "<script type=\"dojo/method\" event=\"onSubmit\" args=\"evt\">
			evt.preventDefault();
		dojo.query('#test_result').attr('innerHTML', '');
		new Ajax.Request('backend.php', {
			parameters: dojo.objectToQuery(this.getValues()),
				onComplete: function(transport) {
					if (transport.responseJSON.success == false){
						notify_error(transport.responseJSON.errormessage);
	}else{
		dojo.query('#test_url').attr('innerHTML', '<pre>'+transport.responseJSON.url+'</pre>');
		dojo.query('#test_result').attr('innerHTML', transport.responseJSON.content);
		dojo.query('#test_log').attr('innerHTML', transport.responseJSON.log.join(\"\\n\"));
	}
	}
	});
	</script>";

		print "Save before you test!<br />";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"op\" value=\"pluginhandler\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"method\" value=\"test\">";
		print "<input dojoType=\"dijit.form.TextBox\" style=\"display : none\" name=\"plugin\" value=\"feediron\">";

		print "<table width='100%'><tr><td>";
		print "<input dojoType=\"dijit.form.TextBox\" name=\"test_url\" style=\"font-size: 12px; width: 99%;\" />";
		print "</td></tr></table>";
		print "<p><button dojoType=\"dijit.form.Button\" type=\"submit\">".__("Test")."</button>";
		print "</form>";
		print "<div data-dojo-type='dijit/layout/TabContainer' style='width: 100%;' doLayout='false'>";
		print "<div data-dojo-type='dijit/layout/ContentPane' title='log' data-dojo-props='selected:true' id='test_log'></div>";
		print "<div data-dojo-type='dijit/layout/ContentPane' title='result' data-dojo-props='selected:true' id='test_result'></div>";
		print "<div data-dojo-type='dijit/layout/ContentPane' title='url' data-dojo-props='selected:true' id='test_url'></div>";
		print "</div>";
	}

	/*
	 * Storing the json reformat data
	 */
	function save()
	{
		$json_conf = $_POST['json_conf'];

		$json_reply = array();
		$this->formatjson($json_conf);
		header('Content-Type: application/json');
		if (is_null(json_decode($json_conf)))
		{
			$json_reply['success'] = false;
			$json_reply['errormessage'] = __('Invalid JSON! ').json_last_error_msg();
			$json_reply['json_error'] = $this->json_error;
			echo json_encode($json_reply);
			return false;
		}

		$this->host->set($this, 'json_conf', $this->formatjson($json_conf));
		$json_reply['success'] = true;
		$json_reply['message'] = __('Configuration saved.');
		$json_reply['json_conf'] = $this->formatjson($json_conf);
		echo json_encode($json_reply);
	}

	/*
	 *  this function tests the rules using a given url
	 */
	function test()
	{
	   $this->is_test = 1;
	   $this->testlog = array();
	   $test_url = $_POST['test_url'];
	   $this->_log("Test url: $test_url");
	   $config = $this->getConfigSection($test_url);
	   $test_url = $this->reformatUrl($test_url, $config);
	   $this->_log("Url after reformat: $test_url");
	   header('Content-Type: application/json');
	   $reply = array();
	   if($config === FALSE)
	   {
		   $reply['success'] = false;
		   $reply['errormessage'] = "URL did not match";
		   echo json_encode($reply);
		   return false;
	   }
	   else
	   {
		   $reply['success'] = true;
		   $reply['url'] = $test_url;
		   $reply['content'] = $this->getNewContent($test_url, $config);
		   $reply['log'] = $this->testlog;
		   echo json_encode($reply);
		}
	}
	/**
	 * Indents a flat JSON string to make it more human-readable.
	 * from http://www.daveperrett.com/articles/2008/03/11/format-json-with-php/
	 *
	 * @param string $json The original JSON string to process.
	 *
	 * @return string Indented version of the original JSON string.
	 */
	function formatjson($json) {

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
			if($char == ' '){
				continue;
			}

			if (array_key_exists($prevChar.$char, $possible_errors)){
				$this->json_error = sprintf($possible_errors[$prevChar.$char]. ' in line %s', substr($result, $this->strrpos_count($result,$newLine,3)), $currentline);
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
	function strrpos_count($haystack, $needle, $count)
	{
		if($count <= 0)
			return false;

		$len = strlen($haystack);
		$pos = $len;

		for($i = 0; $i < $count && $pos; $i++)
			$pos = strrpos($haystack, $needle, $pos - $len - 1);

		return $pos;
	}
}


/*
	json_last_error_msg requires php >= 5.5.0 see http://php.net/manual/en/function.json-last-error-msg.php
	a possible fix would be:
*/

if (!function_exists('json_last_error_msg'))
{
	function json_last_error_msg()
	{
		switch (json_last_error()) {
			default:
				return;
			case JSON_ERROR_DEPTH:
				$error = 'Maximum stack depth exceeded';
			break;
			case JSON_ERROR_STATE_MISMATCH:
				$error = 'Underflow or the modes mismatch';
			break;
			case JSON_ERROR_CTRL_CHAR:
				$error = 'Unexpected control character found';
			break;
			case JSON_ERROR_SYNTAX:
				$error = 'Syntax error, malformed JSON';
			break;
			case JSON_ERROR_UTF8:
				$error = 'Malformed UTF-8 characters, possibly incorrectly encoded';
			break;
		}
		return $error;
	}
}

