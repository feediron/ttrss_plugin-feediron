<?php

//Load bin components
require_once "bin/fi_logger.php";
require_once "bin/fi_json.php";
require_once "bin/fi_helper.php";

//Load PrefTab components
require_once "preftab/fi_pref_tab.php";
require_once "preftab/fi_recipe_manager.php";

//Load Filter modules
spl_autoload_register(function ($class) {
    $file = __DIR__ . DIRECTORY_SEPARATOR . 'filters' . DIRECTORY_SEPARATOR . $class . DIRECTORY_SEPARATOR . 'init.php';
    if(is_readable($file))
        include $file;
});

class Feediron extends Plugin implements IHandler
{
  private $host;
  protected $charset;
  private $json_error;
  private $cache;
  protected $defaults = array(  'debug' => false,
                                'tidy-source' => true);

  // Required API
  function about()
  {
    return array(
      1.23,   // version
      'Reforge your feeds',   // description
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

  // The hook to filter the article. Called for each article
  function hook_article_filter($article)
  {
    Feediron_Logger::get()->set_log_level(0);
    $link = $article["link"];
    if ($link === null) {
      return $article;
    };
    $config = $this->getConfigSection($link);
    if ($config === false) {
      $config = $this->getConfigSection($article['author']);
    }
    if ($config !== false)
    {
      if (version_compare(get_version(), '1.14.0', '<=')){
        $articleMarker = $this->getMarker($article, $config);
        if (strpos($article['plugin_data'], $articleMarker) !== false)
        {
          return $article;
        }

        Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Article was not fetched yet: ".$link);
        $article['plugin_data'] = $this->addArticleMarker($article, $articleMarker);
      }
      $link = $this->reformatUrl($article['link'], $config);

      $NewContent = $this->getNewContent($link, $config);

      // If xpath tags are to replaced tags completely
      if( !empty( $NewContent['tags'] ) AND $NewContent['replace-tags'] ){
        Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Replacing Tags");
        // Overwrite Article tags, Also ensure no empty tags are returned
        $article['tags'] = array_filter( $NewContent['tags'] );
        // If xpath tags are to be prepended to existing tags
      } elseif ( !empty( $NewContent['tags'] ) ) {
        // Merge with in front of Article tags to avoid empty array issues
        $taglist = array_merge($NewContent['tags'], $article['tags']);
        Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Merging Tags: ".implode( ", ", $taglist));
        // Ensure no empty tags are returned
        $article['tags'] = array_filter( $taglist );
      }
      $article['content'] = $NewContent['content'];
    }

    return $article;
  }

  //Creates a marker for the article processed with specific config
  function getMarker($article, $config)
  {
    $articleMarker = mb_strtolower(get_class($this));
    $articleMarker .= ",".$article['owner_uid'].",".md5(print_r($config, true)).":";
    return $articleMarker;
  }

  // Removes old marker and adds new one
  function addArticleMarker($article, $marker)
  {
    return $marker.preg_replace('/'.get_class($this).','.$article['owner_id'].',.*?:/','',$article['plugin_data']);
  }

  function getConfigSection($url)
  {
    if ($url === null) { return false; };
    $data = $this->getConfig();
    if(is_array($data)){

      foreach ($data as $urlpart=>$config) { // Check for multiple URL's
        if (strpos($urlpart, "|") !== false){
          $urlparts = explode("|", $urlpart);
          foreach ($urlparts as $suburl){
            if (strpos($url, $suburl) !== false){
              $urlpart = $suburl;
              break; // exit loop
            }
          }

        }
        if (strpos($url, $urlpart) === false){
          continue;   // skip this config if URL not matching
        }

        foreach ( array_keys( $this->defaults ) as $key ) {
            if( isset( $data[$key] ) && is_bool( $data[$key] ) ) {
                $this->defaults[$key] = $data[$key];
            }
        }

        Feediron_Logger::get()->log_object(Feediron_Logger::LOG_TEST, "Config found", $config);

        if(Feediron_Logger::get()->get_log_level() == 0){
          Feediron_Logger::get()->set_log_level( ( $this->defaults['debug'] ) || !is_array( $data ) );
        }

        return $config;
      }
    }
    return false;
  }

  // Load config
  function getConfig()
  {
    $json_conf = $this->host->get($this, 'json_conf');
    $data = json_decode($json_conf, true);

    return $data;
  }

  // reformat an url with a given config
  function reformatUrl($url, $config)
  {
    $link = trim($url);
    if(is_array($config['reformat']))
    {
      $link = Feediron_Helper::reformat($link, $config['reformat']);
      Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Reformated url: ".$link);
    }
    return $link;
  }

  // grep new content for a link and aplly config
  function getNewContent($link, $config)
  {
    $links = $this->getLinks($link, $config);
    Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Fetching ".count($links)." links");
    Feediron_Logger::get()->log(Feediron_Logger::LOG_TEST, "Fetching ".count($links)." links", join("\n", $links));
    $NewContent['content'] = "";
    $NewContent['replace-tags'] = $config['replace-tags'];
    foreach($links as $lnk)
    {
      $html = $this->getArticleContent($lnk, $config);
      if( isset( $config['tags'] ) )
      {
        $NewContent['tags'] = $this->getArticleTags($html, $config['tags']);
      }
      Feediron_Logger::get()->log_html(Feediron_Logger::LOG_TEST, "Original Source ".$lnk.":", $html);
      $html = $this->processArticle($html, $config, $lnk);
      Feediron_Logger::get()->log_html(Feediron_Logger::LOG_TEST, "Modified Source ".$lnk.":", $html);
      $NewContent['content'] .= $html;
    }
    return $NewContent;

  }

  //extract links for multipage articles
  function getLinks($link, $config)
  {
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
    if(is_array($this->cache) && array_key_exists($link, $this->cache)){
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Fetching from cache");
      return $this->cache[$link];
    }
    list($html, $content_type) = $this->get_content($link);

    $this->charset = false;

    // Array of valid charsets for tidy functions
    $valid_charsets = array(
      "raw" => array("raw"),
      "ascii" => array("ascii"),
      "latin0" => array("latin0"),
      "latin1" => array("latin1"),
      "utf8" => array("utf8", "UTF-8", "ISO88591", "ISO-8859-1", "ISO8859-1"),
      "iso2022" => array("iso2022"),
      "mac" => array("mac"),
      "win1252" => array("win1252"),
      "ibm858" => array("ibm858"),
      "utf16le" => array("utf16le"),
      "utf16be" => array("utf16be"),
      "utf16" => array("utf16"),
      "big5" => array("big5"),
      "shiftjis" => array("shiftjis")
    );

    if (!isset($config['force_charset']))
    {
      if ($content_type)
      {
        preg_match('/charset=(\S+)/', $content_type, $matches);
        if (isset($matches[1]) && !empty($matches[1])) {
          $this->charset = str_replace('"', "", html_entity_decode($matches[1]));
        }
      }
    } elseif ( isset( $config['force_charset'] ) ) {
      // use forced charset
      $this->charset = $config['force_charset'];
    } elseif ( mb_detect_encoding($html, 'UTF-8', true) == 'UTF-8' ) {
      $this->charset = 'UTF-8';
    }

    Feediron_Logger::get()->log(Feediron_Logger::LOG_TEST, "charset:", $this->charset);
    if ($this->charset && isset($config['force_unicode']) && $config['force_unicode'])
    {
      $html = mb_convert_encoding($html, 'HTML-ENTITIES', $this->charset);
      $this->charset = 'utf-8';
      Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Changed charset to utf-8:", $html);
    }

    // Map Charset to valid_charsets
    if ( isset($this->charset) ){
      foreach($valid_charsets as $index => $alias) {

          foreach($alias as $key => $value) {

      		if ($value == $this->charset) {
      			$this->charset = $index;
            Feediron_Logger::get()->log(Feediron_Logger::LOG_TEST, "Valid Charset detected and mapped", $this->charset);
      			break 2;
      		}
      	}
      }
    }

    // Use PHP tidy to fix source page if option tidy-source called
    if ( !isset($config['tidy-source']) ){
        $config['tidy-source'] = $this->defaults['tidy-source'];
    }
    if (function_exists('tidy_parse_string') && $config['tidy-source'] !== false && $this->charset !== false){
        try {
          Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "attempting tidy of source");
          // Use forced or discovered charset of page
          $tidy = tidy_parse_string($html, array('indent'=>true, 'show-body-only' => true), str_replace(["-", "–"], '', $this->charset));
          $tidy->cleanRepair();
          $tidy_html = $tidy->value;
          if( strlen($tidy_html) <= ( strlen($html)/2 )) {
                Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "tidy removed too much content, reverting");
          } else {
                Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "tidy of source completed successfully");
                $html = $tidy_html;
          }
        } catch (Exception $e) {
          Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Error running tidy", $e);
        } catch (Throwable $t) {
          Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Error running tidy", $t);
        }
    }

    Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Writing into cache");
    $this->cache[$link] = $html;

    return $html;
  }

  function getArticleTags( $html, $config )
  {
    // Build settings array
    $settings = array( "charset" => $this->charset );

    $str = 'fi_mod_tags_';
    $class = $str . $config['type'];

    if (class_exists($class)) {
      $tags = ( new $class() )->get_tags($html, $config, $settings);
    } else {
      Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Unrecognized option: ".$config['type']);
    }

    if(!$tags){
      Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "No tags saved");
      return;
    }

    // Split tags
    if( isset( $config['split'] ) )
    {
      $split_tags = array();
      foreach( $tags as $key=>$tag )
      {
        $split_tags = array_merge($split_tags, explode( $config['split'], $tag ) );
      }
      $tags =	$split_tags;
    }

    // Loop through tags indivdually
    foreach( $tags as $key=>$tag )
    {
      // If set perform modify
      if(is_array($config['modify']))
      {
        $tag = Feediron_Helper::reformat($tag, $config['modify']);
      }
      // Strip tags of html and ensure plain text
      $tags[$key] = trim( preg_replace('/\s+/', ' ', strip_tags( $tag ) ) );
      Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Tag saved: ".$tags[$key]);
    }

    $tags = array_filter($tags);

    return $tags;
  }

  function get_content($link)
  {
    global $fetch_last_content_type;
    Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, $link);
    if (version_compare(get_version(), '1.7.9', '>='))
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

  function fetch_links($link, $config, $seenlinks = array())
  {
    Feediron_Logger::get()->log(Feediron_Logger::LOG_TEST, "fetching links from :".$lnk);
    $html = $this->getArticleContent($link, $config);
    $links = $this->extractlinks($html, $config);
    if (count($links) == 0)
    {
      return array($link);
    }
    $links = $this->fixlinks($link, $links);
    if (count(array_intersect($seenlinks, $links)) != 0)
    {
      Feediron_Logger::get()->log_object(Feediron_Logger::LOG_VERBOSE, "Break infinite loop for recursive multipage, link intersection",array_intersect($seenlinks, $links));
      return array($link);
    }
    foreach ($links as $lnk)
    {
      Feediron_Logger::get()->log(Feediron_Logger::LOG_TEST, "link:".$lnk);
      /* If recursive mode is active fetch links from newly fetched link */
      if(isset($config['multipage']['recursive']) && $config['multipage']['recursive'])
      {
        $links =  $this->fetch_links($lnk, $config, array($links, $link));
      }
    }
    if(isset($config['multipage']['append']) && $config['multipage']['append'])
    {
      array_unshift($links, $link);
    }
    /* Avoid link dupplication */
    $links = array_unique($links);
    return $links;

  }

  function extractlinks($html, $config)
  {
    $doc = Feediron_Helper::getDOM( $html, $this->charset, $config['debug'] );
    $links = array();

    $xpath = new DOMXPath($doc);
    /* Extract the links based on xpath */
    $entries = $xpath->query('(//'.$config['multipage']['xpath'].')');

      if ($entries->length < 1){
        return array();
      }
      if($this->loglevel == Feediron_Logger::LOG_VERBOSE){
        $log_entries = array_map( array($this, 'getHtmlNode') , $entries);
        Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Found ".count($entries)." link elements:", join("\n", $log_entries));
      }
      foreach($entries as $entry)
      {
        $links[] = $entry->getAttribute('href');
      }
      return $links;
    }

  function fixlinks($link, $links)
  {
    $retlinks = array();
    foreach($links as $lnk)
    {
      $retlinks[] = $this->resolve_url($link, $lnk);
    }
    return $retlinks;
  }

  /**
  * Does the reverse of parse_url (creates a URL from an associative array of components)
  */
  function unparse_url($parsed_url) {
    $scheme   = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : '';
    $host     = isset($parsed_url['host']) ? $parsed_url['host'] : '';
    $port     = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : '';
    $user     = isset($parsed_url['user']) ? $parsed_url['user'] : '';
    $pass     = isset($parsed_url['pass']) ? ':' . $parsed_url['pass']  : '';
    $pass     = ($user || $pass) ? "$pass@" : '';
    $path     = isset($parsed_url['path']) ? $parsed_url['path'] : '';
    $query    = isset($parsed_url['query']) ? '?' . $parsed_url['query'] : '';
    $fragment = isset($parsed_url['fragment']) ? '#' . $parsed_url['fragment'] : '';
    return "$scheme$user$pass$host$port$path$query$fragment";
  }

  /**
  * Resolve a URL relative to a base path. Based on RFC 2396 section 5.2.
  */
  function resolve_url($base, $url)
  {
    if (!strlen($base)) return $url;
    // Step 2
    if (!strlen($url)) return $base;
    // Step 3
    if (preg_match('!^[a-z]+:!i', $url)) return $url;
    $base = parse_url($base);
    if ($url[0] == "#") {
      // Step 2 (fragment)
      $base['fragment'] = substr($url, 1);
      return $this->unparse_url($base);
    }
    unset($base['fragment']);
    unset($base['query']);
    if (substr($url, 0, 2) == "//") {
      // Step 4
      return $this->unparse_url(array(
        'scheme'=>$base['scheme'],
        'path'=>substr($url,2),
      ));
    } else if ($url[0] == "/") {
      // Step 5
      $base['path'] = $url;
    } else {
      // Step 6
      $path = explode('/', isset($base['path']) ? $base['path'] : "");
      $url_path = explode('/', $url);
      // Step 6a: drop file from base
      array_pop($path);
      // Step 6b, 6c, 6e: append url while removing "." and ".." from
      // the directory portion
      $end = array_pop($url_path);
      foreach ($url_path as $segment) {
        if ($segment == '.') {
          // skip
        } else if ($segment == '..' && $path && $path[sizeof($path)-1] != '..') {
          array_pop($path);
        } else {
          $path[] = $segment;
        }
      }
      // Step 6d, 6f: remove "." and ".." from file portion
      if ($end == '.') {
        $path[] = '';
      } else if ($end == '..' && $path && $path[sizeof($path)-1] != '..') {
        $path[sizeof($path)-1] = '';
      } else {
        $path[] = $end;
      }
      // Step 6h
      $base['path'] = join('/', $path);
    }
    // Step 7
    return $this->unparse_url($base);
  }

  function processArticle($html, $config, $link)
  {
    // Build settings array
    $settings = array( "charset" => $this->charset, "link" => $link );

    $str = 'fi_mod_';
    $class = $str . $config['type'];

    if (class_exists($class)) {
      $html = ( new $class() )->perform_filter($html, $config, $settings);
    } else {
      Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Unrecognized option: ".$config['type']." ".$class);
    }

    if(is_array($config['modify']))
    {
      $html = Feediron_Helper::reformat($html, $config['modify']);
    }
    // if we've got Tidy, let's clean it up for output
    if (function_exists('tidy_parse_string') && $config['tidy'] !== false && $this->charset !== false) {
      try {
        $tidy = tidy_parse_string($html, array('indent'=>true, 'show-body-only' => true), str_replace(["-", "–"], '', $this->charset));
        $tidy->cleanRepair();
        $html = $tidy->value;
      } catch (Exception $e) {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Error running tidy", $e);
      } catch (Throwable $t) {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Error running tidy", $t);
      }
    }
    return $html;
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
    $test_conf = $pluginhost->get($this, 'test_conf');
    print Feediron_PrefTab::get_pref_tab($json_conf, $test_conf);
  }

  /*
  * Storing the json reformat data
  */
  function save()
  {
    $json_conf = $_POST['json_conf'];

    $json_reply = array();
    Feediron_Json::format($json_conf);
    header('Content-Type: application/json');
    if (is_null(json_decode($json_conf)))
    {
      $json_reply['success'] = false;
      $json_reply['errormessage'] = __('Invalid JSON! ').json_last_error_msg();
      $json_reply['json_error'] = Feediron_Json::get_error();
      echo json_encode($json_reply);
      return false;
    }

    $this->host->set($this, 'json_conf', Feediron_Json::format($json_conf));
    $json_reply['success'] = true;
    $json_reply['message'] = __('Configuration saved.');
    $json_reply['json_conf'] = Feediron_Json::format($json_conf);
    echo json_encode($json_reply);
  }

  function export()
  {
    $conf = $this->getConfig();
    $recipe2export = $_POST['recipe'];
    Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "export recipe: ".$recipe2export);
    header('Content-Type: application/json');
    if(!isset ($conf[$recipe2export])){
      $json_reply['success'] = false;
      $json_reply['errormessage'] = __('Not found');
      echo json_encode($json_reply);
      return false;
    }
    $json_reply['success'] = true;
    $json_reply['message'] = __('Exported');

    $sth = $this->pdo->prepare("SELECT full_name FROM ttrss_users WHERE id = ?");
    $sth->execute([$_SESSION['uid']]);
    $author = $sth->fetch();

    $data = array(
      "name"=> (isset($conf[$recipe2export]['name'])?$conf[$recipe2export]['name']:$recipe2export),
      "url" => (isset($conf[$recipe2export]['url'])?$conf[$recipe2export]['url']:$recipe2export),
      "stamp" => time(),
      "author" =>  $author['full_name'],
      "match" => $recipe2export,
      "config" => $conf[$recipe2export]
    );
    $json_reply['json_export'] = Feediron_Json::format(json_encode($data));
    echo json_encode($json_reply);
  }

  function add()
  {
    $conf = $this->getConfig();
    $recipe2add = $_POST['addrecipe'];
    Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "recipe: ".$recipe2add);
    $rm = new RecipeManager();
    $recipe = $rm->getRecipe($recipe2add);
    header('Content-Type: application/json');
    if(!isset ($recipe['match'])){
      $json_reply['success'] = false;
      $json_reply['errormessage'] = __('Github API message: ').$recipe['message'];
      $json_reply['data'] = Feediron_Json::format(json_encode($recipe));
      echo json_encode($json_reply);
      return false;
    }
    if(isset ($conf[$recipe['match']])){
      $conf[$recipe['match'].'_orig'] = $conf[$recipe['match']];
    }
    $conf[$recipe['match']] = $recipe['config'];

    $json_reply['success'] = true;
    $json_reply['message'] = __('Configuration updated.');
    $json_reply['json_conf'] = Feediron_Json::format(json_encode($conf, JSON_UNESCAPED_SLASHES));
    echo json_encode($json_reply);
  }

  function arrayRecursiveDiff($aArray1, $aArray2) {
    $aReturn = array();

    foreach ($aArray1 as $mKey => $mValue) {
      if (array_key_exists($mKey, $aArray2)) {
        if (is_array($mValue)) {
          $aRecursiveDiff = $this->arrayRecursiveDiff($mValue, $aArray2[$mKey]);
          if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
        } else {
          if ($mValue != $aArray2[$mKey]) {
            $aReturn[$mKey] = $mValue;
          }
        }
      } else {
        $aReturn[$mKey] = $mValue;
      }
    }
    return $aReturn;
  }

  /*
  *  this function tests the rules using a given url
  */
  function test()
  {
    Feediron_Logger::get()->set_log_level($_POST['verbose']?Feediron_Logger::LOG_VERBOSE:Feediron_Logger::LOG_TEST);
    $test_url = $_POST['test_url'];
    Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Test url: $test_url");

    if(isset($_POST['test_conf']) && trim($_POST['test_conf']) != ''){

      $json_conf = $_POST['test_conf'];
      $json_reply = array();
      Feediron_Json::format($json_conf);
      header('Content-Type: application/json');
      if (is_null(json_decode($json_conf)))
      {
        $json_reply['success'] = false;
        $json_reply['errormessage'] = __('Invalid JSON! ').json_last_error_msg();
        $json_reply['json_error'] = Feediron_Json::get_error();
        echo json_encode($json_reply);
        return false;
      }

      $config = $this->getConfigSection($test_url);
      $newconfig = json_decode($_POST['test_conf'], true);
      Feediron_Logger::get()->log_object(Feediron_Logger::LOG_TEST, "config posted: ", $newconfig);
      if($config != false){
        Feediron_Logger::get()->log_object(Feediron_Logger::LOG_TEST, "config found: ", $config);
        Feediron_Logger::get()->log_object(Feediron_Logger::LOG_TEST, "config diff", $this->arrayRecursiveDiff($config, $newconfig));
        if(count($this->arrayRecursiveDiff($newconfig, $config))!= 0){
          $this->host->set($this, 'test_conf', Feediron_Json::format(json_encode($config)));
        }
      }
      $config = json_decode($_POST['test_conf'], true);
    }else{
      $config = $this->getConfigSection($test_url);
    }
    Feediron_Logger::get()->log_object(Feediron_Logger::LOG_TEST, "Using config", $config);
    $test_url = $this->reformatUrl($test_url, $config);
    Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Url after reformat: $test_url");
    header('Content-Type: application/json');
    $reply = array();
    if($config === false) {

      $reply['success'] = false;
      $reply['errormessage'] = "URL did not match";
      $reply['log'] = Feediron_Logger::get()->get_testlog();
      echo json_encode($reply);
      return false;

    } else {

      $reply['success'] = true;
      $reply['url'] = $test_url;
      $NewContent = $this->getNewContent($test_url, $config);
      $reply['content'] = $NewContent['content'];
      $reply['config'] = Feediron_Json::format(json_encode($config));
      if($reply['config'] == null){
        $reply['config'] = $_POST['test_conf'];
      }
      $reply['log'] = Feediron_Logger::get()->get_testlog();
      echo json_encode($reply);

    }
  }
}
