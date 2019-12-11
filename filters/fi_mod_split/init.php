<?php

class fi_mod_split
{

  public function perform_filter( $html, $config, $settings ){
    $orig_html = $html;
    foreach($config['steps'] as $step)
    {
      Feediron_Logger::get()->log_object(Feediron_Logger::LOG_VERBOSE, "Perform step: ", $step);
      if(isset($step['after']))
      {
        $result = preg_split($step['after'], $html);
        $html = $result[1];
      }
      if(isset($step['before']))
      {
        $result = preg_split($step['before'], $html);
        $html = $result[0];
      }
      Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Step result", $html);
    }
    if(strlen($html) == 0)
    {
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "removed all content, reverting");
      return $orig_html;
    }
    if(($cconfig = Feediron_Helper::getCleanupConfig($config))!== false)
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

}
