<?php

function arraychk($parent, $array) {
  if ( empty($parent[$array]) ){
    $parent[$array] = '';
    $parent[$array] = array();
  }
  return $parent;
}

function fivefilters($recipe){
  $array = array_filter( preg_split("/\r\n|\n|\r/", $recipe) );
  $config = array(
  "type" => "xpath",
  "xpath" => array() );

  $i = 0;
  foreach ($array as $value) {
    $i++;
    if (strpos($value, 'replace_string(') !== false) {
      $value = trim($value, "replace_string(");
      $varray = explode("): ", $value);
      $config = arraychk($config, "modify");
      $config["modify"]["type"] = "replace";
      $config["modify"] = arraychk($config["modify"], "search");
      $config["modify"] = arraychk($config["modify"], "replace");
      array_push( $config["modify"]["search"], $varray[0] );
      array_push( $config["modify"]["replace"], trim( $varray[1] ) );
      unset($array[$i]);
      continue;
    }
  }

  $i = 1;
  foreach ($array as $value) {
    $i++;
    $tmp = explode(": ", $value);
    switch ($tmp[0]) {
      case "body":
        array_push( $config["xpath"], $tmp[1] );
        continue 2;
      case "strip":
        $config = arraychk($config, "cleanup");
        array_push( $config["cleanup"], $tmp[1] );
        continue 2;
      case "strip_id_or_class":
        $config = arraychk($config, "cleanup");
        array_push( $config["cleanup"], "*[contains(@id,'".$tmp[1]."')]|*[contains(@class,'".$tmp[1]."')]" );
        continue 2;
      case "strip_image_src":
        $config = arraychk($config, "cleanup");
        array_push( $config["cleanup"], "img[contains(@src,'".$tmp[1]."')]" );
        continue 2;
      case "tidy":
        $config["tidy-source"] = true;
        continue 2;
      case "single_page_link":
        $config["multipage"]["xpath"] = $tmp[1];
        $config["multipage"]["append"] = false;
        $config["multipage"]["recursive"] = false;
        continue 2;
      case "next_page_link":
        $config["multipage"]["xpath"] = $tmp[1];
        $config["multipage"]["append"] = true;
        $config["multipage"]["recursive"] = true;
        continue 2;
      case "find_string":
        $config = arraychk($config, "modify");
        $config["modify"] = arraychk($config["modify"], "search");
        array_push( $config["modify"]["search"], $tmp[1] );
        continue 2;
      case "replace_string":
        $config = arraychk($config, "modify");
        $config["modify"] = arraychk($config["modify"], "replace");
        array_push( $config["modify"]["replace"], $tmp[1] );
        continue 2;
      default:
        continue 2;
    }
  }
  return $config;
}

$string = "body: //div[@id=\'article-content\']\nbody: //article[@id=\'entry-top\']/div[@class=\'float_wrapper\']\nauthor: //header/p[@class=\'byline\']/em/a\ndate: //header/p[@class=\'byline\']/span[@class=\'timestamp\']\n\nstrip: //div[@id=\'article-content\']//header\nstrip: //label\nreplace_string(</p>): </div>\n#photos on left column (delete all)\nstrip: //div[@class=\'big_photo\']\n\n#photos on left column (remove extras used for scroll effect)\n#strip: //div[@class=\'big_photo\']/div[./img]\n#strip: //div[@class=\'big_photo\']/img[position()>1]\n\nstrip_id_or_class: vox-lazy-load\nstrip_id_or_class: social_buttons\nstrip_id_or_class: feature_toc\n\nprune: no\n\nfind_string: <noscript>\nreplace_string: <div>\nfind_string: </noscript>\nreplace_string: </div>\n\n#find_string: <script\n#replace_string: <div style=\"display:none\"\n#find_string: </script>\n#replace_string: </div>\n\nstrip: //div[@class=\'float_wrapper\']/header\ntest_url: http://www.polygon.com/2013/4/5/4189028/donkey-kong-country-returns-3d-new-content\ntest_url: http://www.polygon.com/features/2013/8/22/4602568/30-years-xbox-360-playstation-3-wii";
var_dump(fivefilters($string));