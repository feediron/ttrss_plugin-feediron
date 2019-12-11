<?php

class fi_mod_tags_regex
{

  public function get_tags($html, $config, $settings )
  {
    if(!is_array($config['pattern'])){
      $patterns = array($config['pattern']);
    }else{
      $patterns = $config['pattern'];
    }

    if( !isset( $config['index'] ) ){
      $index = 0;
    } else {
      $index = $config['index'];
    }

    // loop through regex pattern array
    foreach( $patterns as $key=>$pattern ){
      preg_match($pattern, $html, $match);
      $tags[$key] = $match[$index];
    }
    return $tags;
  }

}