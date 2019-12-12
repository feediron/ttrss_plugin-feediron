<?php

class fi_mod_tags_xpath
{

  public function get_tags($html, $config, $settings )
  {
    if(!is_array($config['xpath'])){
      $xpaths = array($config['xpath']);
    }else{
      $xpaths = $config['xpath'];
    }

    // loop through xpath array
    foreach( $xpaths as $key=>$xpath )
    {
      // set xpath in config
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Tag xpath", $xpath);
      $newtag = ( new fi_mod_xpath() )->perform_filter( $html, $config, $settings );

      // Filter bad tags
      if( $newtag && $newtag !== $html ){
        $tags[$key] .= $newtag;
        Feediron_Logger::get()->log_html(Feediron_Logger::LOG_TTRSS, "Tag data found: " . $tags[$key]);
      }
    }
    return $tags;
  }
}