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

}