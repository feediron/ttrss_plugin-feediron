<?php

class fi_mod_all_xpath
{

  public function perform_filter( $html, $config, $settings )
  {
    $doc = Feediron_Helper::getDOM( $html, $settings['charset'], $config['debug'] );
    $xpathdom = new DOMXPath($doc);

    $xpaths = Feediron_Helper::check_array( $config['xpath'] );

    $htmlout = array();

    foreach($xpaths as $key=>$xpath){
      Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Perfoming xpath", $xpath);
      $index = 0;
      if(is_array($xpath) && array_key_exists('index', $xpath)){
        $index = $xpath['index'];
        $xpath = $xpath['xpath'];
      }
      $entries = $xpathdom->query('(//'.$xpath.')');   // find main DIV according to config

      $basenode = false;

      if ($entries->length > 0) {
        if($index == 'all'){
           foreach($entries as $entry){
              $this->appendNode($htmlout, $xpathdom, $entry, $config);
           }
        }
        else {
           $basenode = $entries->item($index);
           if($basenode != NULL)
              $this->appendNode($htmlout, $xpathdom, $basenode, $config);
        }
      }

      if (count($htmlout) == 0 && count($xpaths) == ( $key + 1 )) {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "removed all content, reverting");
        return $html;
      } elseif (count($htmlout) == 0 && count($xpaths) > 1){
        continue;
      }
    }

    $content = join((array_key_exists('join_element', $config)?$config['join_element']:''), $htmlout);
    if(array_key_exists('start_element', $config)){
      Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Adding start element", $config['start_element']);
      $content = $config['start_element'].$content;
    }

    if(array_key_exists('end_element', $config)){
      Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Adding end element", $config['end_element']);
      $content = $content.$config['end_element'];
    }

    return $content;
  }

  private function appendNode(&$htmlout, $xpathdom, $basenode, $config){
			Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "append node", $this->getHtmlNode($basenode));
         // remove nodes from cleanup configuration
         $basenode = $this->cleanupNode($xpathdom, $basenode, $config);

         //render nested nodes to html
         $inner_html = $this->getInnerHtml($basenode);
         if (!$inner_html){
            //if there's no nested nodes, render the node itself
            $inner_html = $basenode->ownerDocument->saveXML($basenode);
         }
         array_push($htmlout, $inner_html);
   }

  private function getHtmlNode( $node ){
    if (is_object($node)){
      $newdoc = new DOMDocument();
      if ($node->nodeType == XML_ATTRIBUTE_NODE) {
        // appendChild will fail, so make it a text node
        $imported = $newdoc->createTextNode($node->value);
      } else {
        $cloned = $node->cloneNode(TRUE);
        $imported = $newdoc->importNode($cloned,TRUE);
      }
      $newdoc->appendChild($imported);
      return $newdoc->saveHTML();
    } else {
      return $node;
    }
  }

  private function cleanupNode( $xpath, $basenode, $config )
  {
    if(($cconfig = Feediron_Helper::getCleanupConfig($config))!== FALSE)
    {
      foreach ($cconfig as $cleanup)
      {
        Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "cleanup", $cleanup);
        if(strpos($cleanup, "./") !== 0)
        {
          $cleanup = '//'.$cleanup;
        }
        $nodelist = $xpath->query($cleanup, $basenode);
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
        Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Node after cleanup", $this->getHtmlNode($basenode));
      }
    }
    return $basenode;
  }

  private function getInnerHtml( $node ) {
    $innerHTML= '';
    $children = $node->childNodes;

    foreach ($children as $child) {
      $innerHTML .= $child->ownerDocument->saveXML( $child );
    }

    return $innerHTML;
  }

}
