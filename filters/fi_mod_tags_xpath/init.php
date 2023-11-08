<?php

class_alias('fi_mod_tags_xpath', 'fi_mod_tags_all_xpath');

class fi_mod_tags_xpath
{
  public function get_tags($html, $config, $settings )
  {
    $tags = array(); // initialize tags array
    $xpaths = Feediron_Helper::check_array( $config['xpath'] );

    // loop through xpath array
    foreach( $xpaths as $key=>$xpath )
    {
      // set xpath in config
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Tag xpath", $xpath);
      // Handle multiple tags
      $config['join_element'] = ',';
      $rawtags = ( new fi_mod_xpath() )->perform_filter( $html, $config, $settings );
      
      // Remove non-alphanumeric characters and commas
      $rawtags = preg_replace("/[^a-zA-Z0-9,]/", "", $rawtags);
      $rawtags = explode(',', rtrim($string, ','));
      $rawtags = Feediron_Helper::check_array( $rawtag );
      foreach( $rawtags as $rawtag) {
        $tags = $this->taglist($tags, $rawtag);
      }
    }
    return $tags;
  }

  private function taglist($tags, $rawtag) {
    $newtags = Feediron_Helper::check_array( $rawtag );
    foreach( $newtag as $key=>$newtags ) {
      // Filter bad tags
      if( $newtag && $newtag !== $html ){
        $tags[$key] .= $newtag;
        Feediron_Logger::get()->log_html(Feediron_Logger::LOG_TTRSS, "Tag data found: " . $tags[$key]);
      }
    }
    return $tags;
  }
}