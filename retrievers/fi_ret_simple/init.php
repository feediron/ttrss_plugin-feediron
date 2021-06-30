<?php

class fi_ret_simple
{

  function get_content($html, $config, $settings)
  {
    $fetch_last_content_type = $settings["fetch_last_content_type"];
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

}