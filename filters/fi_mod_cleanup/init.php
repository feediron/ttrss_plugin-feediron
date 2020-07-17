<?php

class fi_mod_cleanup
{

  public function perform_filter( $html, $config, $settings ){

    $doc = Feediron_Helper::getDOM( $html, $settings['charset'], $config['debug'] );
    $cleandom = new DOMXPath($doc);

    if(($cconfig = Feediron_Helper::getCleanupConfig($config))!== FALSE)
    {
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Cleanup ", $cconfig);
      foreach ($cconfig as $cleanup)
      {
        if(strpos($cleanup, "./") !== 0)
        {
          $cleanup = '//'.$cleanup;
        }
        $nodelist = $cleandom->query($cleanup);
        foreach ($nodelist as $node)
        {
          if ($node instanceof DOMAttr)
          {
            $node->ownerElement->removeAttributeNode($node);
          }
          else
          {
            $node->parentNode->removeChild($node);
          }
        }
      }
      return $cleanhdom->saveHTML();
    }
    Feediron_Logger::get()->log(Feediron_Logger::LOG_TTRSS, "Nothing to Cleanup");
    return $html;
  }
}
