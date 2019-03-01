<?php

class Feediron_User{
	public static function get_full_name(){
		$result = db_query("SELECT full_name FROM ttrss_users WHERE id = " . $_SESSION["uid"]);

		return db_fetch_result($result, 0, "full_name");
	}
}

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

  public static function getDOM( $html, $charset, $debug ){
    $doc = new DOMDocument();
    if ($charset) {
			Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Applying Character set:".$charset);
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