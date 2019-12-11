<?php

class fi_mod_tags_search
{

  public function get_tags($html, $config, $settings )
  {
    if(!is_array($config['pattern'])){
      $patterns = array($config['pattern']);
    }else{
      $patterns = $config['pattern'];
    }

    if(!is_array($config['match'])){
      $matches = array($config['match']);
    }else{
      $matches = $config['match'];
    }

    if( count($patterns) != count($matches) ){
      Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Number of Patterns ".count($patterns)." doesn't equal number of Matches ".count($matches));
      return;
    }

    $matches = array_combine ( $patterns, $matches );

    // loop through regex pattern array
    foreach( $matches as $pattern=>$match ){
      if( preg_match($pattern, $html) && substr( $match, 0, 1 ) != "!" ){
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Tag search match", $pattern);
        $tags[$pattern] .= $match;
      } else if( !preg_match($pattern, $html) && substr( $match, 0, 1 ) == "!" ) {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Tag inverted search match", $pattern);
        $tags[$pattern] .= substr( $match, 1 );
      }
    }
    return array_values( $tags );
  }
}
