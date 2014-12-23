<?php

class Feediron_User{
	public static function get_full_name(){
		$result = db_query("SELECT full_name FROM ttrss_users WHERE id = " . $_SESSION["uid"]);

		return db_fetch_result($result, 0, "full_name");
	}
}
