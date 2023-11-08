<?php

class_alias('fi_mod_tags_xpath', 'fi_mod_tags_all_xpath');

class fi_mod_xpath
{

  public function perform_filter( $html, $config, $settings )
  {
    $debug = isset($config['debug']) ? $config['debug'] : false;
    $doc = Feediron_Helper::getDOM( $html, $settings['charset'], $debug );
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
        if ($index === 'all') {
          // Loop through all entries
          foreach ($entries as $entry) {
            // Process each entry
            $this->appendNode($htmlout, $xpathdom, $entry, $config);
          }
        } else {
          // Check if the specified index is within the valid range
          if ($index >= 0 && $index < $entries->length) {
            // Select the specific entry based on the index
            $basenode = $entries->item($index);
            $this->appendNode($htmlout, $xpathdom, $basenode, $config);
          } else {
            // Handle the case where the index is out of range
            Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "Invalid index specified: " + $index);
          }
        }
      }

      if (count($htmlout) == 0) {
        if (count($xpaths) > 1) {
            // Continue to the next iteration if $htmlout is empty and there are more XPath expressions
            continue;
        } elseif (count($xpaths) == ($key + 1)) {
            // Log and return original HTML if $htmlout is empty and it's the last XPath expression
            Feediron_Logger::get()->log(Feediron_Logger::LOG_VERBOSE, "removed all content, reverting");
            return $html;
        }
      }
    }
    if (count($htmlout) > 1) {
      $content = join((array_key_exists('join_element', $config)?$config['join_element']:''), $htmlout);
    }
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
    if (!empty(trim($basenode->nodeValue))) { 
      // remove nodes from cleanup configuration
       $basenode = $this->cleanupNode($xpathdom, $basenode, $config);

       //render nested nodes to html
       $inner_html = $this->getInnerHtml($basenode);
       if (!$inner_html){
          //if there's no nested nodes, render the node itself
          $inner_html = $basenode->ownerDocument->saveXML($basenode);
       }
       $htmlout[] = $inner_html;
    }
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
        if (!$nodelist) {
          Feediron_Logger::get()->log_html(Feediron_Logger::LOG_VERBOSE, "Node not found", $this->getHtmlNode($basenode));
          continue;
        }
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
