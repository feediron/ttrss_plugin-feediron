<?php
class Feediron_Helper
{

  public static function getCleanupConfig( $config )
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
    return $cconfig;
  }

  public static function check_array( $data )
  {

    if(!is_array( $data )){
      $array = array( $data );
    }else{
      $array = $data;
    }

    return $array;

  }

  public static function replaceStringVariableOptions(string|array $replaceString, array $replaceVars) {
    if (is_array($replaceString)) {
      return array_map(function(string|array $element) use ($replaceVars) {
        return self::replaceStringVariableOptions($element, $replaceVars);
      }, $replaceString);
    } else {
      return str_replace(
        array_map(function($k) {
            return '{$'.$k.'}';
        }, array_keys($replaceVars)),
        array_values($replaceVars),
        $replaceString
      );
    }
  }

  // reformat a string with given options
  public static function reformat($string, $options, $articleLink)
  {
    Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Reformat ", $string);
    
    $replaceVars = ['link' => $articleLink];
    $urlComponents = parse_url($articleLink);
    if (is_array($urlComponents)) {
      $replaceVars = array_merge($replaceVars, $urlComponents);
    }

    foreach($options as $option)
    {
      Feediron_Logger::get()->log_object(Feediron_Logger::LOG_VERBOSE, "Reformat step with option ", $option);
      // Check for any string to be replaced in the replace part.
      $replaceString = self::replaceStringVariableOptions($option['replace'], $replaceVars);
      // Log modification of replacement only if really happened.
      if ($option['replace'] != $replaceString) {
        Feediron_Logger::get()->log_object(Feediron_Logger::LOG_VERBOSE, "Reformat step - replace value: ", $replaceString);
      }

      switch($option['type'])
      {
        case 'replace':
          $string = str_replace($option['search'], $replaceString, $string);
          break;

        case 'regex':
        if( isset( $option['count']) ){
          $string = preg_replace($option['pattern'], $replaceString, $string, $option['count']);
        } else {
          $string = preg_replace($option['pattern'], $replaceString, $string);
        }
        break;
      }
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Step result ", $string);
    }
    Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Result ", $string);
    return $string;
  }

  public static function getDOM( $html, $charset, $debug ){
    $doc = new DOMDocument();
    if ($charset) {
			Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Applying Character set: ".$charset);
      $html = '<?xml encoding="' . $charset . '">' . $html;
    }
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    if(!$doc)
    {
      Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "The content is not a valid xml format");
      if( $debug )
      {
        foreach (libxml_get_errors() as $value)
        {
          Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, $value);
        }
      }
      return new DOMDocument();
    }
    return $doc;
  }

}
