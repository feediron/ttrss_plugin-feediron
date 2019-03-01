<?php

//Load bin components
require_once "bin/Logger.php";
require_once "bin/Json.php";
require_once "bin/Helper.php";

//Load PrefTab components
require_once "preftab/PrefTab.php";
require_once "preftab/RecipeManager.php";

//Load Filter modules
require_once "modules/mod_xpath.php";

//Load Tag Filter modules
require_once "modules/mod_tags_regex.php";
require_once "modules/mod_tags_search.php";
require_once "modules/mod_tags_xpath.php";

//Load Composer autoloader hiding errors
@include('lib/vendor/autoload.php');

//Load Readability.php
use andreskrey\Readability\Readability as ReadabilityPHP;
use andreskrey\Readability\Configuration as ReadabilityPHPConf;

class Feediron extends Plugin implements IHandler
{
  private $host;
  protected $charset;
  private $json_error;
  private $cache;
  protected $debug;

  // Required API
  function about()
  {
    return array(
      1.20,   // version
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
    if ($config === FALSE) {
      $config = $this->getConfigSection($article['author']);
    }
    if ($config !== FALSE)
    {
      if (version_compare(VERSION, '1.14.0', '<=')){
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
  function getMarker($article, $config){
    $articleMarker = mb_strtolower(get_class($this));
    $articleMarker .= ",".$article['owner_uid'].",".md5(print_r($config, true)).":";
    return $articleMarker;
  }

  // Removes old marker and adds new one
  function addArticleMarker($article, $marker){
    return $marker.preg_replace('/'.get_class($this).','.$article['owner_id'].',.*?:/','',$article['plugin_data']);
  }

  function getConfigSection($url)
  {
    if ($url === null) { return FALSE; };
    $data = $this->getConfig();
    if(is_array($data)){

      foreach ($data as $urlpart=>$config) { // Check for multiple URL's
        if (strpos($urlpart, "|") !== false){
          $urlparts = explode("|", $urlpart);
          foreach ($urlparts as $suburl){
            if (strpos($url, $suburl) !== false){
              Feediron_Logger::get()->log_object(Feediron_Logger::LOG_TEST, "Config found for $suburl", $config);
              return $config; // Return config if any url matched
            }
          }

        } else {
          if (strpos($url, $urlpart) === false){
            continue;   // skip this config if URL not matching
          }
          Feediron_Logger::get()->log_object(Feediron_Logger::LOG_TEST, "Config found", $config);
          return $config;
        }
      }
    }
    return FALSE;
  }

  // Load config
  function getConfig()
  {
    $json_conf = $this->host->get($this, 'json_conf');
    $data = json_decode($json_conf, true);

    $this->debug = $data['debug'];

    if(Feediron_Logger::get()->get_log_level() == 0){
      Feediron_Logger::get()->set_log_level( ( isset( $this->debug ) && $this->debug ) || !is_array( $data ) );
    }
    if(!is_array($data)){
      Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "No Config found");
    }
    return $data;
  }

  // reformat an url with a given config
  function reformatUrl($url, $config)
  {
    $link = trim($url);
    if(is_array($config['reformat']))
    {
      $link = $this->reformat($link, $config['reformat']);
      Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Reformated url: ".$link);
    }
    return $link;
  }

  // reformat a string with given options
  function reformat($string, $options)
  {
    Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Reformat ", $string);
    foreach($options as $option)
    {
      Feediron_Logger::get()->log_object(Feediron_Logger::LOG_VERBOSE, "Reformat step with option ", $option);
      switch($option['type'])
      {
        case 'replace':
        $string = str_replace($option['search'], $option['replace'], $string);
        break;

        case 'regex':
        $string = preg_replace($option['pattern'], $option['replace'], $string);
        break;
      }
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Step result ", $string);
    }
    Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Result ", $string);
    return $string;
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
    if(is_array($this->cache) && array_key_exists($link, $this->cache)){
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Fetching from cache");
      return $this->cache[$link];
    }
    list($html, $content_type) = $this->get_content($link);

    $this->charset = false;
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
      $html = iconv($this->charset, 'utf-8', $html);
      $this->charset = 'utf-8';
      Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Changed charset to utf-8:", $html);
    }

    // Use PHP tidy to fix source page if option tidy-source called
    if (function_exists('tidy_parse_string') && $config['tidy-source'] == true && $this->charset !== false){
      // Use forced or discovered charset of page
      $tidy = tidy_parse_string($html, array('indent'=>true, 'show-body-only' => true), str_replace(["-", "–"], '', $this->charset));
      $tidy->cleanRepair();
      $html = $tidy->value;
    }

    Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Writing into cache");
    $this->cache[$link] = $html;

    return $html;
  }

  function getArticleTags($html, $config)
  {

    switch ($config['type'])
    {
      case 'xpath':
        $tags = ( new mod_tags_xpath() )->get_tags( $html, $config );
        break;

      case 'regex':
        $tags = ( new mod_tags_regex() )->get_tags( $html, $config );
        break;

      case 'search':
        $tags = ( new mod_tags_search() )->get_tags( $html, $config );
        break;

      default:
        Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Unrecognized option: ".$config['type']);
        break;
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
        $tag = $this->reformat($tag, $config['modify']);
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
      return array();
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
    $doc = Feediron_Helper::getDOM( $html, $this->charset, $this->debug );
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
    function resolve_url($base, $url) {
      if (!strlen($base)) return $url;
      // Step 2
      if (!strlen($url)) return $base;
      // Step 3
      if (preg_match('!^[a-z]+:!i', $url)) return $url;
      $base = parse_url($base);
      if ($url{0} == "#") {
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
      } else if ($url{0} == "/") {
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
      switch ($config['type'])
      {
        case 'readability':
        $html = $this->performReadability($html, $config, $link);
        break;

        case 'split':
        $html = $this->performSplit($html, $config);
        break;

        case 'xpath':
        $html = ( new mod_xpath() )->perform_xpath( $html, $config );
        break;

        default:
        Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Unrecognized option: ".$config['type']);
        break;
      }
      if(is_array($config['modify']))
      {
        $html = $this->reformat($html, $config['modify']);
      }
      // if we've got Tidy, let's clean it up for output
      if (function_exists('tidy_parse_string') && $config['tidy'] !== false && $this->charset !== false) {
        $tidy = tidy_parse_string($html, array('indent'=>true, 'show-body-only' => true), str_replace(["-", "–"], '', $this->charset));
        $tidy->cleanRepair();
        $html = $tidy->value;
      }
      return $html;
    }

    function performReadability($html, $config, $link){

      if (class_exists(ReadabilityPHP::class)) {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Using Readability.php");

        $configuration = new ReadabilityPHPConf();
        if( isset( $config['relativeurl'] ) ) {
          Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php fixing relative URLS ".$config['relativeurl']);
          $configuration
          ->setFixRelativeURLs( true )
          ->setOriginalURL( $config['relativeurl'] );
        }
        if( isset( $config['normalize'] ) && is_bool( $config['normalize'] )  ) {
          Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php Normalizing content");
          $configuration
          ->setNormalizeEntities( $config['normalize'] );
        }
        if( isset( $config['removebyline'] ) && is_bool( $config['removebyline'] )  ) {
          Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php Removing ByLine");
          $configuration
          ->setArticleByLine( $config['removebyline'] );
        }
        //Load Readability with Configuration
        $readability = new ReadabilityPHP( $configuration );

        try {

          $readability->parse($html);

        } catch (Exception $e) {

          //Return unmodified html if Readability.php fails
          Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Readability.php failed to find content");
          return $html;

        }
        if( isset( $config['prependimage'] ) && ( $config['prependimage'] )  ) {
          Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php Prepending Main Image");
          $image = $readability->getImage();
          $content = '<img src="'.$image.'"></img>';
          $content .= $readability->getContent();
        }
        elseif( isset( $config['mainimage'] ) && ( $config['mainimage'] )  ) {
          $image = $readability->getImage();
          $content = '<img src="'.$image.'"></img>';
        }
        elseif( isset( $config['appendimages'] ) && ( $config['apendimages'] )  ) {
          $images = $readability->getImages();
          $content = $readability->getContent();
          foreach ( $images as $image ) {
            $content.='<img src="'.$image.'"></img><br>';
          }
        }
        elseif( isset( $config['allimages'] ) && ( $config['allimages'] )  ) {
          $images = $readability->getImages();
          foreach ( $images as $image ) {
            $content.='<img src="'.$image.'"></img><br>';
          }
        } else {
          $content = $readability->getContent();
        }
      }
      else {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Using Legacy Readability");

        require_once 'lib/php-readability/Readability.php';
        require_once 'lib/php-readability/JSLikeHTMLElement.php';
        $readability = new Readability\Readability($html, $link);
        $readability->debug = false;
        $readability->convertLinksToFootnotes = true;
        $result = $readability->init();
        if (!$result) {
          Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability failed to find content");
          return $html;
        }
        else {
          $content = $readability->getContent()->innerHTML;
          Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Readability modified Source ".$lnk.":", $html);
        }
      }
      // Perform xpath on readability output
      if (isset($config['xpath'])){
        $html = ( new mod_xpath() )->perform_xpath( $html, $config );
        // If no xpath for readability output perform simple cleanup
      } elseif(($cconfig = $this->getCleanupConfig($config))!== FALSE) {
        $html = $content;
        foreach($cconfig as $cleanup){
          Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Cleaning up", $cleanup);
          $html = preg_replace($cleanup, '', $html);
          Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "cleanup  result", $html);
        }
      } else {
        // If no extra config just return the content
        $html = $content;
      }
      return $html;
    }

    function performSplit($html, $config){
      $orig_html = $html;
      foreach($config['steps'] as $step)
      {
        Feediron_Logger::get()->log_object(Feediron_Logger::LOG_VERBOSE, "Perform step: ", $step);
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
        Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Step result", $html);
      }
      if(strlen($html) == 0)
      {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "removed all content, reverting");
        return $orig_html;
      }
      if(($cconfig = $this->getCleanupConfig($config))!== FALSE)
      {
        foreach($cconfig as $cleanup)
        {
          Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Cleaning up", $cleanup);
          $html = preg_replace($cleanup, '', $html);
          Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "cleanup  result", $html);
        }
      }
      return $html;
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
        Feediron_Logger::get()->log_object(Feediron_Logger::LOG_VERBOSE, "Cleanup config", $cconfig);
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

      function export(){
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
        $data = array(
          "name"=> (isset($conf[$recipe2export]['name'])?$conf[$recipe2export]['name']:$recipe2export),
          "url" => (isset($conf[$recipe2export]['url'])?$conf[$recipe2export]['url']:$recipe2export),
          "stamp" => time(),
          "author" => Feediron_User::get_full_name(),
          "match" => $recipe2export,
          "config" => $conf[$recipe2export]
        );
        $json_reply['json_export'] = Feediron_Json::format(json_encode($data));
        echo json_encode($json_reply);
      }

      function add(){
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
          if($config != False){
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
        if($config === FALSE) {

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
