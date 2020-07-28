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
      $content = ( new fi_mod_xpath() )->perform_filter( $html, $config, $settings );
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
