<?php

//Load Composer autoloader hiding errors
@include('vendor/autoload.php');

//Load Readability.php
use andreskrey\Readability\Readability as ReadabilityPHP;
use andreskrey\Readability\Configuration as ReadabilityPHPConf;

class fi_mod_readability
{

  public function perform_filter( $html, $config, $settings ){

    if (class_exists(ReadabilityPHP::class)) {
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Using Readability.php");

      $configuration = new ReadabilityPHPConf();

      //Define Readability Configuration
      foreach ($config as $key => $value) {
        switch ($key) {

          case "relativeurl":
            Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php fixing relative URLS ".$value);
            $configuration
            ->setFixRelativeURLs( true )
            ->setOriginalURL( $value );
            continue 2;

          case "normalize":
            if(!is_bool($value)) continue 2;

            Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php Normalizing content");
            $configuration
            ->setNormalizeEntities( $config['normalize'] );
            continue 2;

          case "removebyline":
            if(!is_bool($value)) continue 2;

            Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php Removing ByLine");
            $configuration
            ->setArticleByLine( $config['removebyline'] );
            continue 2;

        }
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

      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php Fetching Main body text");
      $content = $readability->getContent();

      //Define Main Content
      foreach ($config as $key => $value) {
        switch ($key) {

          case "excerpt":
            if(!$value) continue 2;

            Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php Fetching Excerpt");
            $content = $readability->getExcerpt();
            break 2;

          case "mainimage":
            if(!$value) continue 2;

            Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php Fetching Main Image");
            $image = $readability->getImage();
            $content = '<img src="'.$image.'"></img>';
            break 2;

          case "allimages":
            if(!$value) continue 2;

            Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php Fetching All Images");
            $images = $readability->getImages();
            foreach ( $images as $image ) {
              $content.='<img src="'.$image.'"></img><br>';
            }
            break 2;

        }
      }

      //Append/Prepend additional content
      if( isset( $config['prependexcerpt'] ) && ( $config['prependexcerpt'] ) && !( $config['excerpt'] )  ) {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php Prepending Excerpt");
        $excerpt = $readability->getExcerpt();
        $content = $excerpt.'<br><hr><details><summary>Full Article</summary>'.$content.'</details>';
      }

      if( isset( $config['prependimage'] ) && ( $config['prependimage'] )  ) {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability.php Prepending Main Image");
        $image = $readability->getImage();
        $content = '<img src="'.$image.'"></img><br>'.$content;
      }

      if( isset( $config['appendimages'] ) && ( $config['apendimages'] )  ) {
        $images = $readability->getImages();
        foreach ( $images as $image ) {
          $content.='<img src="'.$image.'"></img><br>';
        }
      }

    } else {
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Using Legacy Readability");

      require_once 'php-readability/Readability.php';
      require_once 'php-readability/JSLikeHTMLElement.php';
      $readability = new Readability\Readability($html, $settings['link']);
      $readability->debug = false;
      $readability->convertLinksToFootnotes = true;
      $result = $readability->init();
      if (!$result) {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Readability failed to find content");
        return $html;
      }
      else {
        $content = $readability->getContent()->innerHTML;
        Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Readability modified Source ".$settings['link'].":", $content);
      }
    }
    // Perform xpath on readability output
    if (isset($config['xpath'])){
      $content = ( new fi_mod_xpath() )->perform_filter( $content, $config, $settings );
      // If no xpath for readability output perform simple cleanup
    } elseif(($cconfig = Feediron_Helper::getCleanupConfig($config))!== false) {
      foreach($cconfig as $cleanup){
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Cleaning up", $cleanup);
        $content = preg_replace($cleanup, '', $content);
        Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "cleanup  result", $content);
      }
    }

    return $content;
  }
}
