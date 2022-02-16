<?php

namespace Readability;

use DOMElement;
use Masterminds\HTML5;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Readability implements LoggerAwareInterface
{
    // flags
    public const FLAG_STRIP_UNLIKELYS = 1;
    public const FLAG_WEIGHT_ATTRIBUTES = 2;
    public const FLAG_CLEAN_CONDITIONALLY = 4;
    public const FLAG_DISABLE_PREFILTER = 8;
    public const FLAG_DISABLE_POSTFILTER = 16;
    // constants
    public const SCORE_CHARS_IN_PARAGRAPH = 100;
    public const SCORE_WORDS_IN_PARAGRAPH = 20;
    public const GRANDPARENT_SCORE_DIVISOR = 2;
    public const MIN_PARAGRAPH_LENGTH = 20;
    public const MIN_COMMAS_IN_PARAGRAPH = 6;
    public const MIN_ARTICLE_LENGTH = 200;
    public const MIN_NODE_LENGTH = 80;
    public const MAX_LINK_DENSITY = 0.25;
    public $convertLinksToFootnotes = false;
    public $revertForcedParagraphElements = false;
    public $articleTitle;
    public $articleContent;
    public $original_html;
    /**
     * @var \DOMDocument
     */
    public $dom;
    // optional - URL where HTML was retrieved
    public $url = null;
    // preserves more content (experimental)
    public $lightClean = true;
    // no more used, keept to avoid BC
    public $debug = false;
    public $tidied = false;

    /**
     * All of the regular expressions in use within readability.
     * Defined up here so we don't instantiate them repeatedly in loops.
     */
    public $regexps = [
        'unlikelyCandidates' => '/-ad-|ai2html|banner|breadcrumbs|combx|comment|community|cover-wrap|disqus|extra|footer|gdpr|header|legends|menu|related|remark|replies|rss|shoutbox|sidebar|skyscraper|social|sponsor|supplemental|ad-break|agegate|pagination|pager|popup|yom-remote/i',
        'okMaybeItsACandidate' => '/article\b|contain|\bcontent|column|general|detail|shadow|lightbox|blog|body|entry|main|page|footnote|element/i',
        'positive' => '/read|full|article|body|\bcontent|contain|entry|main|markdown|media|page|attach|pagination|post|text|blog|story/i',
        'negative' => '/bottom|stat|info|discuss|e[\-]?mail|comment|reply|log.{2}(n|ed)|sign|single|combx|com-|contact|_nav|link|media|promo|\bad-|related|scroll|shoutbox|sidebar|sponsor|shopping|teaser|recommend/i',
        'divToPElements' => '/<(?:blockquote|header|section|code|div|article|footer|aside|img|p|pre|dl|ol|ul)/mi',
        'killBreaks' => '/(<br\s*\/?>([ \r\n\s]|&nbsp;?)*)+/',
        'media' => '!//(?:[^\.\?/]+\.)?(?:youtu(?:be)?|giphy|soundcloud|dailymotion|vimeo|pornhub|xvideos|twitvid|rutube|openload\.co|viddler)\.(?:com|be|org|net)/!i',
        'skipFootnoteLink' => '/^\s*(\[?[a-z0-9]{1,2}\]?|^|edit|citation needed)\s*$/i',
        'hasContent' => '/\S$/',
        'isNotVisible' => '/display\s*:\s*none/',
    ];
    public $defaultTagsToScore = ['section', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'td', 'pre'];
    // The commented out elements qualify as phrasing content but tend to be
    // removed by readability when put into paragraphs, so we ignore them here.
    public $phrasingElements = [
        // "CANVAS", "IFRAME", "SVG", "VIDEO",
        'ABBR', 'AUDIO', 'B', 'BDO', 'BR', 'BUTTON', 'CITE', 'CODE', 'DATA',
        'DATALIST', 'DFN', 'EM', 'EMBED', 'I', 'IMG', 'INPUT', 'KBD', 'LABEL',
        'MARK', 'MATH', 'METER', 'NOSCRIPT', 'OBJECT', 'OUTPUT', 'PROGRESS', 'Q',
        'RUBY', 'SAMP', 'SCRIPT', 'SELECT', 'SMALL', 'SPAN', 'STRONG', 'SUB',
        'SUP', 'TEXTAREA', 'TIME', 'VAR', 'WBR',
    ];
    public $tidy_config = [
        'tidy-mark' => false,
        'vertical-space' => false,
        'doctype' => 'omit',
        'numeric-entities' => false,
        // 'preserve-entities' => true,
        'break-before-br' => false,
        'clean' => false,
        'output-xhtml' => true,
        'logical-emphasis' => true,
        'show-body-only' => false,
        'new-blocklevel-tags' => 'article aside audio bdi canvas details dialog figcaption figure footer header hgroup main menu menuitem nav section source summary template track video',
        'new-empty-tags' => 'command embed keygen source track wbr',
        'new-inline-tags' => 'audio command datalist embed keygen mark menuitem meter output progress source time video wbr',
        'wrap' => 0,
        'drop-empty-paras' => true,
        'drop-proprietary-attributes' => false,
        'enclose-text' => true,
        'merge-divs' => true,
        // 'merge-spans' => true,
        'input-encoding' => '????',
        'output-encoding' => 'utf8',
        'hide-comments' => true,
    ];
    // article domain regexp for calibration
    protected $domainRegExp = null;
    protected $body = null;
    // Cache the body HTML in case we need to re-use it later
    protected $bodyCache = null;
    // 1 | 2 | 4;   // Start with all processing flags set.
    protected $flags = 7;
    // indicates whether we were able to extract or not
    protected $success = false;
    protected $logger;
    protected $parser;
    protected $html;
    protected $useTidy;
    // raw HTML filters
    protected $pre_filters = [
        // remove obvious scripts
        '!<script[^>]*>(.*?)</script>!is' => '',
        // remove obvious styles
        '!<style[^>]*>(.*?)</style>!is' => '',
        // remove spans as we redefine styles and they're probably special-styled
        '!</?span[^>]*>!is' => '',
        // HACK: firewall-filtered content
        '!<font[^>]*>\s*\[AD\]\s*</font>!is' => '',
        // HACK: replace linebreaks plus br's with p's
        '!(<br[^>]*>[ \r\n\s]*){2,}!i' => '</p><p>',
        // replace noscripts
        //'!</?noscript>!is' => '',
        // replace fonts to spans
        '!<(/?)font[^>]*>!is' => '<\\1span>',
    ];
    // output HTML filters
    protected $post_filters = [
        // replace excessive br's
        '/<br\s*\/?>\s*<p/i' => '<p',
        // replace empty tags that break layouts
        '!<(?:a|div|p|figure)[^>]+/>!is' => '',
        // remove all attributes on text tags
        //'!<(\s*/?\s*(?:blockquote|br|hr|code|div|article|span|footer|aside|p|pre|dl|li|ul|ol)) [^>]+>!is' => "<\\1>",
        //single newlines cleanup
        "/\n+/" => "\n",
        // modern web...
        '!<pre[^>]*>\s*<code!is' => '<pre',
        '!</code>\s*</pre>!is' => '</pre>',
        '!<[hb]r>!is' => '<\\1 />',
    ];

    /**
     * Create instance of Readability.
     *
     * @param string $html    UTF-8 encoded string
     * @param string $url     URL associated with HTML (for footnotes)
     * @param string $parser  Which parser to use for turning raw HTML into a DOMDocument
     * @param bool   $useTidy Use tidy
     */
    public function __construct(string $html, string $url = null, string $parser = 'libxml', bool $useTidy = true)
    {
        $this->url = $url;
        $this->html = $html;
        $this->parser = $parser;
        $this->useTidy = $useTidy && \function_exists('tidy_parse_string');

        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Get article title element.
     *
     * @return DOMElement
     */
    public function getTitle()
    {
        return $this->articleTitle;
    }

    /**
     * Get article content element.
     *
     * @return DOMElement
     */
    public function getContent()
    {
        return $this->articleContent;
    }

    /**
     * Add pre filter for raw input HTML processing.
     *
     * @param string $filter   RegExp for replace
     * @param string $replacer Replacer
     */
    public function addPreFilter(string $filter, string $replacer = ''): void
    {
        $this->pre_filters[$filter] = $replacer;
    }

    /**
     * Add post filter for raw output HTML processing.
     *
     * @param string $filter   RegExp for replace
     * @param string $replacer Replacer
     */
    public function addPostFilter(string $filter, string $replacer = ''): void
    {
        $this->post_filters[$filter] = $replacer;
    }

    /**
     * Runs readability.
     *
     * Workflow:
     *  1. Prep the document by removing script tags, css, etc.
     *  2. Build readability's DOM tree.
     *  3. Grab the article content from the current dom tree.
     *  4. Replace the current DOM tree with the new one.
     *  5. Read peacefully.
     *
     * @return bool true if we found content, false otherwise
     */
    public function init(): bool
    {
        $this->loadHtml();

        if (!isset($this->dom->documentElement)) {
            return false;
        }

        // Assume successful outcome
        $this->success = true;
        $bodyElems = $this->dom->getElementsByTagName('body');

        // WTF multiple body nodes?
        if (null === $this->bodyCache) {
            $this->bodyCache = '';
            foreach ($bodyElems as $bodyNode) {
                $this->bodyCache .= trim($bodyNode->getInnerHTML());
            }
        }

        if ($bodyElems->length > 0 && null === $this->body) {
            $this->body = $bodyElems->item(0);
        }

        $this->prepDocument();

        // Build readability's DOM tree.
        $overlay = $this->dom->createElement('div');
        $innerDiv = $this->dom->createElement('div');
        $articleTitle = $this->getArticleTitle();
        $articleContent = $this->grabArticle();

        if (!$articleContent) {
            $this->success = false;
            $articleContent = $this->dom->createElement('div');
            $articleContent->setAttribute('class', 'readability-content');
            $articleContent->setInnerHtml('<p>Sorry, Readability was unable to parse this page for content.</p>');
        }

        $overlay->setAttribute('class', 'readOverlay');
        $innerDiv->setAttribute('class', 'readInner');

        // Glue the structure of our document together.
        $innerDiv->appendChild($articleTitle);
        $innerDiv->appendChild($articleContent);
        $overlay->appendChild($innerDiv);

        // without tidy the body can (sometimes) be wiped, so re-create it
        if (false === isset($this->body->childNodes)) {
            $this->body = $this->dom->createElement('body');
        }

        // Clear the old HTML, insert the new content.
        $this->body->setInnerHtml('');
        $this->body->appendChild($overlay);
        $this->body->removeAttribute('style');
        $this->postProcessContent($articleContent);

        // Set title and content instance variables.
        $this->articleTitle = $articleTitle;
        $this->articleContent = $articleContent;

        return $this->success;
    }

    /**
     * Run any post-process modifications to article content as necessary.
     */
    public function postProcessContent(DOMElement $articleContent): void
    {
        if ($this->convertLinksToFootnotes && !preg_match('/\bwiki/', $this->url)) {
            $this->addFootnotes($articleContent);
        }
    }

    /**
     * For easier reading, convert this document to have footnotes at the bottom rather than inline links.
     *
     * @see http://www.roughtype.com/archives/2010/05/experiments_in.php
     */
    public function addFootnotes(DOMElement $articleContent): void
    {
        $footnotesWrapper = $this->dom->createElement('footer');
        $footnotesWrapper->setAttribute('class', 'readability-footnotes');
        $footnotesWrapper->setInnerHtml('<h3>References</h3>');
        $articleFootnotes = $this->dom->createElement('ol');
        $articleFootnotes->setAttribute('class', 'readability-footnotes-list');
        $footnotesWrapper->appendChild($articleFootnotes);
        $articleLinks = $articleContent->getElementsByTagName('a');
        $linkCount = 0;

        for ($i = 0; $i < $articleLinks->length; ++$i) {
            $articleLink = $articleLinks->item($i);
            $footnoteLink = $articleLink->cloneNode(true);
            $refLink = $this->dom->createElement('a');
            $footnote = $this->dom->createElement('li');
            $linkDomain = @parse_url($footnoteLink->getAttribute('href'), \PHP_URL_HOST);
            if (!$linkDomain && isset($this->url)) {
                $linkDomain = @parse_url($this->url, \PHP_URL_HOST);
            }

            $linkText = $this->getInnerText($articleLink);
            if ((false !== strpos($articleLink->getAttribute('class'), 'readability-DoNotFootnote')) || preg_match($this->regexps['skipFootnoteLink'], $linkText)) {
                continue;
            }

            ++$linkCount;

            // Add a superscript reference after the article link.
            $refLink->setAttribute('href', '#readabilityFootnoteLink-' . $linkCount);
            $refLink->setInnerHtml('<small><sup>[' . $linkCount . ']</sup></small>');
            $refLink->setAttribute('class', 'readability-DoNotFootnote');
            $refLink->setAttribute('style', 'color: inherit;');

            if ($articleLink->parentNode->lastChild->isSameNode($articleLink)) {
                $articleLink->parentNode->appendChild($refLink);
            } else {
                $articleLink->parentNode->insertBefore($refLink, $articleLink->nextSibling);
            }

            $articleLink->setAttribute('style', 'color: inherit; text-decoration: none;');
            $articleLink->setAttribute('name', 'readabilityLink-' . $linkCount);
            $footnote->setInnerHtml('<small><sup><a href="#readabilityLink-' . $linkCount . '" title="Jump to Link in Article">^</a></sup></small> ');
            $footnoteLink->setInnerHtml(('' !== $footnoteLink->getAttribute('title') ? $footnoteLink->getAttribute('title') : $linkText));
            $footnoteLink->setAttribute('name', 'readabilityFootnoteLink-' . $linkCount);
            $footnote->appendChild($footnoteLink);

            if ($linkDomain) {
                $footnote->setInnerHtml($footnote->getInnerHTML() . '<small> (' . $linkDomain . ')</small>');
            }
            $articleFootnotes->appendChild($footnote);
        }

        if ($linkCount > 0) {
            $articleContent->appendChild($footnotesWrapper);
        }
    }

    /**
     * Prepare the article node for display. Clean out any inline styles,
     * iframes, forms, strip extraneous <p> tags, etc.
     */
    public function prepArticle(\DOMNode $articleContent): void
    {
        if (!$articleContent instanceof DOMElement) {
            return;
        }

        $this->logger->debug($this->lightClean ? 'Light clean enabled.' : 'Standard clean enabled.');

        $this->cleanStyles($articleContent);
        $this->killBreaks($articleContent);

        $xpath = new \DOMXPath($articleContent->ownerDocument);

        if ($this->revertForcedParagraphElements) {
            /*
             * Reverts P elements with class 'readability-styled' to text nodes:
             * which is what they were before.
             */
            $elems = $xpath->query('.//p[@data-readability-styled]', $articleContent);
            for ($i = $elems->length - 1; $i >= 0; --$i) {
                $e = $elems->item($i);
                $e->parentNode->replaceChild($articleContent->ownerDocument->createTextNode($e->textContent), $e);
            }
        }

        // Remove service data-candidate attribute.
        $elems = $xpath->query('.//*[@data-candidate]', $articleContent);
        for ($i = $elems->length - 1; $i >= 0; --$i) {
            $elems->item($i)->removeAttribute('data-candidate');
        }

        // Clean out junk from the article content.
        $this->clean($articleContent, 'input');
        $this->clean($articleContent, 'button');
        $this->clean($articleContent, 'nav');
        $this->clean($articleContent, 'object');
        $this->clean($articleContent, 'iframe');
        $this->clean($articleContent, 'canvas');
        $this->clean($articleContent, 'h1');

        /*
         * If there is only one h2, they are probably using it as a main header, so remove it since we
         *  already have a header.
         */
        $h2s = $articleContent->getElementsByTagName('h2');
        if (1 === $h2s->length && mb_strlen($this->getInnerText($h2s->item(0), true, true)) < 100) {
            $this->clean($articleContent, 'h2');
        }

        $this->cleanHeaders($articleContent);

        // Do these last as the previous stuff may have removed junk that will affect these.
        $this->cleanConditionally($articleContent, 'form');
        $this->cleanConditionally($articleContent, 'table');
        $this->cleanConditionally($articleContent, 'ul');
        $this->cleanConditionally($articleContent, 'div');

        // Remove extra paragraphs.
        $articleParagraphs = $articleContent->getElementsByTagName('p');

        for ($i = $articleParagraphs->length - 1; $i >= 0; --$i) {
            $item = $articleParagraphs->item($i);

            $imgCount = $item->getElementsByTagName('img')->length;
            $embedCount = $item->getElementsByTagName('embed')->length;
            $objectCount = $item->getElementsByTagName('object')->length;
            $videoCount = $item->getElementsByTagName('video')->length;
            $audioCount = $item->getElementsByTagName('audio')->length;
            $iframeCount = $item->getElementsByTagName('iframe')->length;

            if (0 === $iframeCount && 0 === $imgCount && 0 === $embedCount && 0 === $objectCount && 0 === $videoCount && 0 === $audioCount && 0 === mb_strlen(preg_replace('/\s+/is', '', $this->getInnerText($item, false, false)))) {
                $item->parentNode->removeChild($item);
            }

            // add extra text to iframe tag to avoid an auto-closing iframe and then break the html code
            if ($iframeCount) {
                $iframe = $item->getElementsByTagName('iframe');
                $iframe->item(0)->nodeValue = ' ';

                $item->parentNode->replaceChild($iframe->item(0), $item);
            }
        }

        if (!$this->flagIsActive(self::FLAG_DISABLE_POSTFILTER)) {
            try {
                foreach ($this->post_filters as $search => $replace) {
                    $articleContent->setInnerHtml(preg_replace($search, $replace, $articleContent->getInnerHTML()));
                }
                unset($search, $replace);
            } catch (\Exception $e) {
                $this->logger->error('Cleaning output HTML failed. Ignoring: ' . $e->getMessage());
            }
        }
    }

    /**
     * Get the inner text of a node.
     * This also strips out any excess whitespace to be found.
     *
     * @param DOMElement $e
     * @param bool       $normalizeSpaces (default: true)
     * @param bool       $flattenLines    (default: false)
     */
    public function getInnerText($e, bool $normalizeSpaces = true, bool $flattenLines = false): string
    {
        if (null === $e || !isset($e->textContent) || '' === $e->textContent) {
            return '';
        }

        $textContent = trim($e->textContent);

        if ($flattenLines) {
            return (string) mb_ereg_replace('(?:[\r\n](?:\s|&nbsp;)*)+', '', $textContent);
        }

        if ($normalizeSpaces) {
            return (string) mb_ereg_replace('\s\s+', ' ', $textContent);
        }

        return $textContent;
    }

    /**
     * Remove the style attribute on every $e and under.
     */
    public function cleanStyles(DOMElement $e): void
    {
        if (\is_object($e)) {
            $elems = $e->getElementsByTagName('*');

            foreach ($elems as $elem) {
                $elem->removeAttribute('style');
            }
        }
    }

    /**
     * Get comma number for a given text.
     */
    public function getCommaCount(string $text): int
    {
        return \count(explode(',', $text));
    }

    /**
     * Get words number for a given text if words separated by a space.
     * Input string should be normalized.
     */
    public function getWordCount(string $text): int
    {
        return substr_count($text, ' ');
    }

    /**
     * Get the density of links as a percentage of the content
     * This is the amount of text that is inside a link divided by the total text in the node.
     * Can exclude external references to differentiate between simple text and menus/infoblocks.
     */
    public function getLinkDensity(DOMElement $e, bool $excludeExternal = false): float
    {
        $links = $e->getElementsByTagName('a');
        $textLength = mb_strlen($this->getInnerText($e, true, true));
        $linkLength = 0;

        for ($dRe = $this->domainRegExp, $i = 0, $il = $links->length; $i < $il; ++$i) {
            if ($excludeExternal && $dRe && !preg_match($dRe, $links->item($i)->getAttribute('href'))) {
                continue;
            }
            $linkLength += mb_strlen($this->getInnerText($links->item($i)));
        }

        if ($textLength > 0 && $linkLength > 0) {
            return $linkLength / $textLength;
        }

        return 0;
    }

    /**
     * Get an element relative weight.
     */
    public function getWeight(DOMElement $e): int
    {
        if (!$this->flagIsActive(self::FLAG_WEIGHT_ATTRIBUTES)) {
            return 0;
        }

        $weight = 0;
        // Look for a special classname
        $weight += $this->weightAttribute($e, 'class');
        // Look for a special ID
        $weight += $this->weightAttribute($e, 'id');

        return $weight;
    }

    /**
     * Remove extraneous break tags from a node.
     */
    public function killBreaks(DOMElement $node): void
    {
        $html = $node->getInnerHTML();
        $html = preg_replace($this->regexps['killBreaks'], '<br />', $html);
        $node->setInnerHtml($html);
    }

    /**
     * Clean a node of all elements of type "tag".
     * (Unless it's a youtube/vimeo video. People love movies.).
     *
     * Updated 2012-09-18 to preserve youtube/vimeo iframes
     */
    public function clean(DOMElement $e, string $tag): void
    {
        $targetList = $e->getElementsByTagName($tag);
        $isEmbed = ('audio' === $tag || 'video' === $tag || 'iframe' === $tag || 'object' === $tag || 'embed' === $tag);

        for ($y = $targetList->length - 1; $y >= 0; --$y) {
            // Allow youtube and vimeo videos through as people usually want to see those.
            $currentItem = $targetList->item($y);

            if ($isEmbed) {
                $attributeValues = $currentItem->getAttribute('src') . ' ' . $currentItem->getAttribute('href');

                // First, check the elements attributes to see if any of them contain known media hosts
                if (preg_match($this->regexps['media'], $attributeValues)) {
                    continue;
                }

                // Then check the elements inside this element for the same.
                if (preg_match($this->regexps['media'], $targetList->item($y)->getInnerHTML())) {
                    continue;
                }
            }

            $currentItem->parentNode->removeChild($currentItem);
        }
    }

    /**
     * Clean an element of all tags of type "tag" if they look fishy.
     * "Fishy" is an algorithm based on content length, classnames,
     * link density, number of images & embeds, etc.
     */
    public function cleanConditionally(DOMElement $e, string $tag): void
    {
        if (!$this->flagIsActive(self::FLAG_CLEAN_CONDITIONALLY)) {
            return;
        }

        $tagsList = $e->getElementsByTagName($tag);
        $curTagsLength = $tagsList->length;

        /*
         * Gather counts for other typical elements embedded within.
         * Traverse backwards so we can remove nodes at the same time without effecting the traversal.
         *
         * TODO: Consider taking into account original contentScore here.
         */
        for ($i = $curTagsLength - 1; $i >= 0; --$i) {
            $node = $tagsList->item($i);
            $weight = $this->getWeight($node);
            $contentScore = ($node->hasAttribute('readability')) ? (int) $node->getAttribute('readability') : 0;
            $this->logger->debug('Start conditional cleaning of ' . $node->getNodePath() . ' (class=' . $node->getAttribute('class') . '; id=' . $node->getAttribute('id') . ')' . (($node->hasAttribute('readability')) ? (' with score ' . $node->getAttribute('readability')) : ''));

            // XXX Incomplete implementation
            $isList = \in_array($node->tagName, ['ul', 'ol'], true);

            if ($weight + $contentScore < 0) {
                $this->logger->debug('Removing...');
                $node->parentNode->removeChild($node);
            } elseif ($this->getCommaCount($this->getInnerText($node)) < self::MIN_COMMAS_IN_PARAGRAPH) {
                /*
                 * If there are not very many commas, and the number of
                 * non-paragraph elements is more than paragraphs or other ominous signs, remove the element.
                 */
                $p = $node->getElementsByTagName('p')->length;
                $img = $node->getElementsByTagName('img')->length;
                $li = $node->getElementsByTagName('li')->length - 100;
                $input = $node->getElementsByTagName('input')->length;
                $a = $node->getElementsByTagName('a')->length;
                $embedCount = 0;
                $embeds = $node->getElementsByTagName('embed');

                for ($ei = 0, $il = $embeds->length; $ei < $il; ++$ei) {
                    if (preg_match($this->regexps['media'], $embeds->item($ei)->getAttribute('src'))) {
                        ++$embedCount;
                    }
                }

                $embeds = $node->getElementsByTagName('iframe');
                for ($ei = 0, $il = $embeds->length; $ei < $il; ++$ei) {
                    if (preg_match($this->regexps['media'], $embeds->item($ei)->getAttribute('src'))) {
                        ++$embedCount;
                    }
                }

                $linkDensity = $this->getLinkDensity($node, true);
                $contentLength = mb_strlen($this->getInnerText($node));
                $toRemove = false;

                if ($this->lightClean) {
                    if (!$isList && $li > $p) {
                        $this->logger->debug(' too many <li> elements, and parent is not <ul> or <ol>');
                        $toRemove = true;
                    } elseif ($input > floor($p / 3)) {
                        $this->logger->debug(' too many <input> elements');
                        $toRemove = true;
                    } elseif (!$isList && $contentLength < 6 && (0 === $embedCount && (0 === $img || $img > 2))) {
                        $this->logger->debug(' content length less than 6 chars, 0 embeds and either 0 images or more than 2 images');
                        $toRemove = true;
                    } elseif (!$isList && $weight < 25 && $linkDensity > 0.25) {
                        $this->logger->debug(' weight is ' . $weight . ' < 25 and link density is ' . sprintf('%.2f', $linkDensity) . ' > 0.25');
                        $toRemove = true;
                    } elseif ($a > 2 && ($weight >= 25 && $linkDensity > 0.5)) {
                        $this->logger->debug('  more than 2 links and weight is ' . $weight . ' > 25 but link density is ' . sprintf('%.2f', $linkDensity) . ' > 0.5');
                        $toRemove = true;
                    } elseif ($embedCount > 3) {
                        $this->logger->debug(' more than 3 embeds');
                        $toRemove = true;
                    }
                } else {
                    if ($img > $p) {
                        $this->logger->debug(' more image elements than paragraph elements');
                        $toRemove = true;
                    } elseif (!$isList && $li > $p) {
                        $this->logger->debug('  too many <li> elements, and parent is not <ul> or <ol>');
                        $toRemove = true;
                    } elseif ($input > floor($p / 3)) {
                        $this->logger->debug('  too many <input> elements');
                        $toRemove = true;
                    } elseif (!$isList && $contentLength < 10 && (0 === $img || $img > 2)) {
                        $this->logger->debug('  content length less than 10 chars and 0 images, or more than 2 images');
                        $toRemove = true;
                    } elseif (!$isList && $weight < 25 && $linkDensity > 0.2) {
                        $this->logger->debug('  weight is ' . $weight . ' lower than 0 and link density is ' . sprintf('%.2f', $linkDensity) . ' > 0.2');
                        $toRemove = true;
                    } elseif ($weight >= 25 && $linkDensity > 0.5) {
                        $this->logger->debug('  weight above 25 but link density is ' . sprintf('%.2f', $linkDensity) . ' > 0.5');
                        $toRemove = true;
                    } elseif ((1 === $embedCount && $contentLength < 75) || $embedCount > 1) {
                        $this->logger->debug('  1 embed and content length smaller than 75 chars, or more than one embed');
                        $toRemove = true;
                    }
                }

                if ($toRemove) {
                    $this->logger->debug('Removing...');
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

    /**
     * Clean out spurious headers from an Element. Checks things like classnames and link density.
     */
    public function cleanHeaders(DOMElement $e): void
    {
        for ($headerIndex = 1; $headerIndex < 3; ++$headerIndex) {
            $headers = $e->getElementsByTagName('h' . $headerIndex);

            for ($i = $headers->length - 1; $i >= 0; --$i) {
                if ($this->getWeight($headers->item($i)) < 0 || $this->getLinkDensity($headers->item($i)) > 0.33) {
                    $headers->item($i)->parentNode->removeChild($headers->item($i));
                }
            }
        }
    }

    /**
     * Check if the given flag is active.
     */
    public function flagIsActive(int $flag): bool
    {
        return ($this->flags & $flag) > 0;
    }

    /**
     * Add a flag.
     */
    public function addFlag(int $flag): void
    {
        $this->flags = $this->flags | $flag;
    }

    /**
     * Remove a flag.
     */
    public function removeFlag(int $flag): void
    {
        $this->flags = $this->flags & ~$flag;
    }

    /**
     * Get the article title as an H1.
     *
     * @return DOMElement
     */
    protected function getArticleTitle()
    {
        try {
            $curTitle = $origTitle = $this->getInnerText($this->dom->getElementsByTagName('title')->item(0));
        } catch (\Exception $e) {
            $curTitle = '';
            $origTitle = '';
        }

        if (preg_match('/ [\|\-] /', $curTitle)) {
            $curTitle = preg_replace('/(.*)[\|\-] .*/i', '$1', $origTitle);
            if (\count(explode(' ', $curTitle)) < 3) {
                $curTitle = preg_replace('/[^\|\-]*[\|\-](.*)/i', '$1', $origTitle);
            }
        } elseif (false !== strpos($curTitle, ': ')) {
            $curTitle = preg_replace('/.*:(.*)/i', '$1', $origTitle);
            if (\count(explode(' ', $curTitle)) < 3) {
                $curTitle = preg_replace('/[^:]*[:](.*)/i', '$1', $origTitle);
            }
        } elseif (mb_strlen($curTitle) > 150 || mb_strlen($curTitle) < 15) {
            $hOnes = $this->dom->getElementsByTagName('h1');
            if (1 === $hOnes->length) {
                $curTitle = $this->getInnerText($hOnes->item(0));
            }
        }

        $curTitle = trim($curTitle);
        if (\count(explode(' ', $curTitle)) <= 4) {
            $curTitle = $origTitle;
        }

        $articleTitle = $this->dom->createElement('h1');
        $articleTitle->setInnerHtml($curTitle);

        return $articleTitle;
    }

    /**
     * Prepare the HTML document for readability to scrape it.
     * This includes things like stripping javascript, CSS, and handling terrible markup.
     */
    protected function prepDocument(): void
    {
        /*
         * In some cases a body element can't be found (if the HTML is totally hosed for example)
         * so we create a new body node and append it to the document.
         */
        if (null === $this->body) {
            $this->body = $this->dom->createElement('body');
            $this->dom->documentElement->appendChild($this->body);
        }

        $this->body->setAttribute('class', 'readabilityBody');

        // Remove all style tags in head.
        $styleTags = $this->dom->getElementsByTagName('style');
        for ($i = $styleTags->length - 1; $i >= 0; --$i) {
            $styleTags->item($i)->parentNode->removeChild($styleTags->item($i));
        }

        $linkTags = $this->dom->getElementsByTagName('link');
        for ($i = $linkTags->length - 1; $i >= 0; --$i) {
            $linkTags->item($i)->parentNode->removeChild($linkTags->item($i));
        }
    }

    /**
     * Initialize a node with the readability object. Also checks the
     * className/id for special names to add to its score.
     */
    protected function initializeNode(DOMElement $node): void
    {
        if (!isset($node->tagName)) {
            return;
        }

        $readability = $this->dom->createAttribute('readability');
        // this is our contentScore
        $readability->value = 0;
        $node->setAttributeNode($readability);

        // using strtoupper just in case
        switch (strtoupper($node->tagName)) {
            case 'ARTICLE':
                $readability->value += 15;
                // no break
            case 'DIV':
                $readability->value += 5;
                break;
            case 'PRE':
            case 'CODE':
            case 'TD':
            case 'BLOCKQUOTE':
            case 'FIGURE':
                $readability->value += 3;
                break;
            case 'SECTION':
                // often misused
                // $readability->value += 2;
                break;
            case 'OL':
            case 'UL':
            case 'DL':
            case 'DD':
            case 'DT':
            case 'LI':
                $readability->value -= 3;
                break;
            case 'ASIDE':
            case 'FOOTER':
            case 'HEADER':
            case 'ADDRESS':
            case 'FORM':
            case 'BUTTON':
            case 'TEXTAREA':
            case 'INPUT':
            case 'NAV':
                $readability->value -= 3;
                break;
            case 'H1':
            case 'H2':
            case 'H3':
            case 'H4':
            case 'H5':
            case 'H6':
            case 'TH':
            case 'HGROUP':
                $readability->value -= 5;
                break;
        }

        $readability->value += $this->getWeight($node);
    }

    /**
     * Using a variety of metrics (content score, classname, element types), find the content that is
     * most likely to be the stuff a user wants to read. Then return it wrapped up in a div.
     *
     * @param DOMElement $page
     *
     * @return DOMElement|false
     */
    protected function grabArticle(DOMElement $page = null)
    {
        if (!$page) {
            $page = $this->dom;
        }

        $xpath = null;
        $nodesToScore = [];

        if ($page instanceof \DOMDocument && isset($page->documentElement)) {
            $xpath = new \DOMXPath($page);
        }

        $allElements = $page->getElementsByTagName('*');

        for ($nodeIndex = 0; $allElements->item($nodeIndex); ++$nodeIndex) {
            $node = $allElements->item($nodeIndex);
            $tagName = $node->tagName;

            $nodeContent = $node->getInnerHTML();
            if (empty($nodeContent)) {
                $this->logger->debug('Skipping empty node');
                continue;
            }

            // Remove invisible nodes
            if (!$this->isNodeVisible($node)) {
                $this->logger->debug('Removing invisible node ' . $node->getNodePath());
                $node->parentNode->removeChild($node);
                --$nodeIndex;
                continue;
            }

            // Remove unlikely candidates
            $unlikelyMatchString = $node->getAttribute('class') . ' ' . $node->getAttribute('id') . ' ' . $node->getAttribute('style');

            if (mb_strlen($unlikelyMatchString) > 3 && // don't process "empty" strings
                preg_match($this->regexps['unlikelyCandidates'], $unlikelyMatchString) &&
                !preg_match($this->regexps['okMaybeItsACandidate'], $unlikelyMatchString)
            ) {
                $this->logger->debug('Removing unlikely candidate (using conf) ' . $node->getNodePath() . ' by "' . $unlikelyMatchString . '"');
                $node->parentNode->removeChild($node);
                --$nodeIndex;
                continue;
            }

            // Some well known site uses sections as paragraphs.
            if (\in_array($tagName, $this->defaultTagsToScore, true)) {
                $nodesToScore[] = $node;
            }

            // Turn divs into P tags where they have been used inappropriately
            //  (as in, where they contain no other block level elements).
            if ('div' === $tagName) {
                if (!preg_match($this->regexps['divToPElements'], $nodeContent)) {
                    $newNode = $this->dom->createElement('p');

                    try {
                        $newNode->setInnerHtml($nodeContent);

                        $node->parentNode->replaceChild($newNode, $node);
                        --$nodeIndex;
                        $nodesToScore[] = $newNode;
                    } catch (\Exception $e) {
                        $this->logger->error('Could not alter div/article to p, reverting back to div: ' . $e->getMessage());
                    }
                } else {
                    // Will change these P elements back to text nodes after processing.
                    $p = null;
                    // foreach does not handle removeChild very well
                    // See https://www.php.net/manual/en/domnode.removechild.php#90292
                    $childs = iterator_to_array($node->childNodes);
                    foreach ($childs as $childNode) {
                        // executable tags (<?php or <?xml) warning
                        if ($childNode instanceof \DOMProcessingInstruction) {
                            $childNode->parentNode->removeChild($childNode);

                            continue;
                        }

                        if ($childNode instanceof \DOMText && '' === $this->getInnerText($childNode, true, true)) {
                            /* $this->logger->debug('Remove empty text node'); */
                            $childNode->parentNode->removeChild($childNode);

                            continue;
                        }

                        if ($this->isPhrasingContent($childNode)) {
                            if (null !== $p) {
                                $p->appendChild($childNode);
                            } elseif ('' !== $this->getInnerText($childNode, true, true)) {
                                $p = $this->dom->createElement('p');
                                $p->setAttribute('data-readability-styled', 'true');
                                $node->replaceChild($p, $childNode);
                                $p->appendChild($childNode);
                            }
                        } elseif (null !== $p) {
                            while ($p->lastChild && '' === $this->getInnerText($p->lastChild, true, true)) {
                                $p->removeChild($p->lastChild);
                            }
                            $p = null;
                        }
                    }

                    if ($this->hasSingleTagInsideElement($node, 'p') && $this->getLinkDensity($node) < 0.25) {
                        $newNode = $node->childNodes->item(0);
                        $node->parentNode->replaceChild($newNode, $node);
                        $nodesToScore[] = $newNode;
                    }
                }
            }
        }

        /*
         * Loop through all paragraphs, and assign a score to them based on how content-y they look.
         * Then add their score to their parent node.
         *
         * A score is determined by things like number of commas, class names, etc.
         * Maybe eventually link density.
         */
        for ($pt = 0, $scored = \count($nodesToScore); $pt < $scored; ++$pt) {
            $ancestors = $this->getAncestors($nodesToScore[$pt], 5);

            // No parent node? Move on...
            if (0 === \count($ancestors)) {
                continue;
            }

            $innerText = $this->getInnerText($nodesToScore[$pt]);

            // If this paragraph is less than MIN_PARAGRAPH_LENGTH (default:20) characters, don't even count it.
            if (mb_strlen($innerText) < self::MIN_PARAGRAPH_LENGTH) {
                continue;
            }

            // Add a point for the paragraph itself as a base.
            $contentScore = 1;
            // Add points for any commas within this paragraph.
            $contentScore += $this->getCommaCount($innerText);
            // For every SCORE_CHARS_IN_PARAGRAPH (default:100) characters in this paragraph, add another point. Up to 3 points.
            $contentScore += min(floor(mb_strlen($innerText) / self::SCORE_CHARS_IN_PARAGRAPH), 3);
            // For every SCORE_WORDS_IN_PARAGRAPH (default:20) words in this paragraph, add another point. Up to 3 points.
            //$contentScore += min(floor($this->getWordCount($innerText) / self::SCORE_WORDS_IN_PARAGRAPH), 3);

            foreach ($ancestors as $level => $ancestor) {
                if (!$ancestor->nodeName || !$ancestor->parentNode) {
                    return;
                }

                if (!$ancestor->hasAttribute('readability')) {
                    $this->initializeNode($ancestor);
                    $ancestor->setAttribute('data-candidate', 'true');
                }

                if (0 === $level) {
                    $scoreDivider = 1;
                } elseif (1 === $level) {
                    $scoreDivider = 2;
                } else {
                    $scoreDivider = $level * 3;
                }
                $ancestor->getAttributeNode('readability')->value += $contentScore / $scoreDivider;
            }
        }

        /*
         * Node prepping: trash nodes that look cruddy (like ones with the class name "comment", etc).
         * This is faster to do before scoring but safer after.
         */
        if ($this->flagIsActive(self::FLAG_STRIP_UNLIKELYS) && $xpath) {
            $candidates = $xpath->query('.//*[(self::footer and count(//footer)<2) or (self::aside and count(//aside)<2)]', $page->documentElement);

            for ($c = $candidates->length - 1; $c >= 0; --$c) {
                $node = $candidates->item($c);
                // node should be readable but not inside of an article otherwise it's probably non-readable block
                if ($node->hasAttribute('readability') && (int) $node->getAttributeNode('readability')->value < 40 && ($node->parentNode ? 0 !== strcasecmp($node->parentNode->tagName, 'article') : true)) {
                    $this->logger->debug('Removing unlikely candidate (using note) ' . $node->getNodePath() . ' by "' . $node->tagName . '" with readability ' . ($node->hasAttribute('readability') ? (int) $node->getAttributeNode('readability')->value : 0));
                    $node->parentNode->removeChild($node);
                }
            }

            $candidates = $xpath->query('.//*[not(self::body) and (@class or @id or @style) and ((number(@readability) < 40) or not(@readability))]', $page->documentElement);

            for ($c = $candidates->length - 1; $c >= 0; --$c) {
                $node = $candidates->item($c);
            }
            unset($candidates);
        }

        /*
         * After we've calculated scores, loop through all of the possible candidate nodes we found
         * and find the one with the highest score.
         */
        $topCandidates = array_fill(0, 5, null);
        if ($xpath) {
            // Using array of DOMElements after deletion is a path to DOOMElement.
            $candidates = $xpath->query('.//*[@data-candidate]', $page->documentElement);
            $this->logger->debug('Candidates: ' . $candidates->length);

            for ($c = $candidates->length - 1; $c >= 0; --$c) {
                $item = $candidates->item($c);

                // Scale the final candidates score based on link density. Good content should have a
                // relatively small link density (5% or less) and be mostly unaffected by this operation.
                // If not for this we would have used XPath to find maximum @readability.
                $readability = $item->getAttributeNode('readability');
                $readability->value = round($readability->value * (1 - $this->getLinkDensity($item)), 0, \PHP_ROUND_HALF_UP);

                for ($t = 0; $t < 5; ++$t) {
                    $aTopCandidate = $topCandidates[$t];

                    if (!$aTopCandidate || $readability->value > (int) $aTopCandidate->getAttribute('readability')) {
                        $this->logger->debug('Candidate: ' . $item->getNodePath() . ' (' . $item->getAttribute('class') . ':' . $item->getAttribute('id') . ') with score ' . $readability->value);
                        array_splice($topCandidates, $t, 0, [$item]);
                        if (\count($topCandidates) > 5) {
                            array_pop($topCandidates);
                        }
                        break;
                    }
                }
            }
        }

        $topCandidates = array_filter($topCandidates, function ($v, $idx) {
            return 0 === $idx || null !== $v;
        }, \ARRAY_FILTER_USE_BOTH);
        $topCandidate = $topCandidates[0];

        /*
         * If we still have no top candidate, just use the body as a last resort.
         * We also have to copy the body node so it is something we can modify.
         */
        if (null === $topCandidate || 0 === strcasecmp($topCandidate->tagName, 'body')) {
            $topCandidate = $this->dom->createElement('div');

            if ($page instanceof \DOMDocument) {
                if (!isset($page->documentElement)) {
                    // we don't have a body either? what a mess! :)
                    $this->logger->debug('The page has no body!');
                } else {
                    $this->logger->debug('Setting body to a raw HTML of original page!');
                    $topCandidate->setInnerHtml($page->documentElement->getInnerHTML());
                    $page->documentElement->setInnerHtml('');
                    $this->reinitBody();
                    $page->documentElement->appendChild($topCandidate);
                }
            } else {
                $topCandidate->setInnerHtml($page->getInnerHTML());
                $page->setInnerHtml('');
                $page->appendChild($topCandidate);
            }

            $this->initializeNode($topCandidate);
        } elseif ($topCandidate) {
            $alternativeCandidateAncestors = [];
            foreach ($topCandidates as $candidate) {
                if ((int) $candidate->getAttribute('readability') / (int) $topCandidate->getAttribute('readability') >= 0.75) {
                    $ancestors = $this->getAncestors($candidate);
                    $this->logger->debug('Adding ' . \count($ancestors) . ' alternative ancestors for ' . $candidate->getNodePath());
                    $alternativeCandidateAncestors[] = $ancestors;
                }
            }
            if (\count($alternativeCandidateAncestors) >= 3) {
                $parentOfTopCandidate = $topCandidate->parentNode;
                while ('body' !== $parentOfTopCandidate->nodeName) {
                    $listsContainingThisAncestor = 0;
                    for ($ancestorIndex = 0; $ancestorIndex < \count($alternativeCandidateAncestors) && $listsContainingThisAncestor < 3; ++$ancestorIndex) {
                        $listsContainingThisAncestor += (int) \in_array($parentOfTopCandidate, $alternativeCandidateAncestors[$ancestorIndex], true);
                    }
                    if ($listsContainingThisAncestor >= 3) {
                        $topCandidate = $parentOfTopCandidate;
                        break;
                    }
                    $parentOfTopCandidate = $parentOfTopCandidate->parentNode;
                }
            }
            if (!$topCandidate->hasAttribute('readability')) {
                $this->initializeNode($topCandidate);
            }
            $parentOfTopCandidate = $topCandidate->parentNode;
            $lastScore = (int) $topCandidate->getAttribute('readability');
            $scoreThreshold = $lastScore / 3;
            while ('body' !== $parentOfTopCandidate->nodeName) {
                if (!$parentOfTopCandidate->hasAttribute('readability')) {
                    $parentOfTopCandidate = $parentOfTopCandidate->parentNode;
                    continue;
                }
                $parentScore = (int) $parentOfTopCandidate->getAttribute('readability');
                if ($parentScore < $scoreThreshold) {
                    break;
                }
                if ($parentScore > $lastScore) {
                    $topCandidate = $parentOfTopCandidate;
                    break;
                }
                $lastScore = (int) $parentOfTopCandidate->getAttribute('readability');
                $parentOfTopCandidate = $parentOfTopCandidate->parentNode;
            }
            $parentOfTopCandidate = $topCandidate->parentNode;
            while ('body' !== $parentOfTopCandidate->nodeName && 1 === $parentOfTopCandidate->childNodes->length) {
                $topCandidate = $parentOfTopCandidate;
                $parentOfTopCandidate = $topCandidate->parentNode;
            }
            if (!$topCandidate->hasAttribute('readability')) {
                $this->initializeNode($topCandidate);
            }
        }

        // Set table as the main node if resulted data is table element.
        $tagName = $topCandidate->tagName;
        if (0 === strcasecmp($tagName, 'td') || 0 === strcasecmp($tagName, 'tr')) {
            $up = $topCandidate;

            if ($up->parentNode instanceof DOMElement) {
                $up = $up->parentNode;

                if (0 === strcasecmp($up->tagName, 'table')) {
                    $topCandidate = $up;
                }
            }
        }

        $this->logger->debug('Top candidate: ' . $topCandidate->getNodePath());

        /*
         * Now that we have the top candidate, look through its siblings for content that might also be related.
         * Things like preambles, content split by ads that we removed, etc.
         */
        $articleContent = $this->dom->createElement('div');
        $articleContent->setAttribute('class', 'readability-content');
        $siblingScoreThreshold = max(10, ((int) $topCandidate->getAttribute('readability')) * 0.2);
        $parentOfTopCandidate = $topCandidate->parentNode;
        $siblingNodes = $parentOfTopCandidate->childNodes;

        if (0 === $siblingNodes->length) {
            $siblingNodes = new \stdClass();
            $siblingNodes->length = 0;
        }

        for ($s = 0, $sl = $siblingNodes->length; $s < $sl; ++$s) {
            $siblingNode = $siblingNodes->item($s);
            $siblingNodeName = $siblingNode->nodeName;
            $append = false;
            $this->logger->debug('Looking at sibling node: ' . $siblingNode->getNodePath() . ((\XML_ELEMENT_NODE === $siblingNode->nodeType && $siblingNode->hasAttribute('readability')) ? (' with score ' . $siblingNode->getAttribute('readability')) : ''));

            if ($siblingNode->isSameNode($topCandidate)) {
                $append = true;
            } else {
                $contentBonus = 0;

                // Give a bonus if sibling nodes and top candidates have the same classname.
                if (\XML_ELEMENT_NODE === $siblingNode->nodeType && $siblingNode->getAttribute('class') === $topCandidate->getAttribute('class') && '' !== $topCandidate->getAttribute('class')) {
                    $contentBonus += ((int) $topCandidate->getAttribute('readability')) * 0.2;
                }

                if (\XML_ELEMENT_NODE === $siblingNode->nodeType && $siblingNode->hasAttribute('readability') && (((int) $siblingNode->getAttribute('readability')) + $contentBonus) >= $siblingScoreThreshold) {
                    $append = true;
                } elseif (0 === strcasecmp($siblingNodeName, 'p')) {
                    $linkDensity = (int) $this->getLinkDensity($siblingNode);
                    $nodeContent = $this->getInnerText($siblingNode, true, true);
                    $nodeLength = mb_strlen($nodeContent);

                    if (($nodeLength > self::MIN_NODE_LENGTH && $linkDensity < self::MAX_LINK_DENSITY)
                        || ($nodeLength < self::MIN_NODE_LENGTH && 0 === $nodeLength && 0 === $linkDensity && preg_match('/\.( |$)/', $nodeContent))) {
                        $append = true;
                    }
                }
            }

            if ($append) {
                $this->logger->debug('Appending node: ' . $siblingNode->getNodePath());

                if (0 !== strcasecmp($siblingNodeName, 'div') && 0 !== strcasecmp($siblingNodeName, 'p')) {
                    // We have a node that isn't a common block level element, like a form or td tag. Turn it into a div so it doesn't get filtered out later by accident.
                    $this->logger->debug('Altering siblingNode "' . $siblingNodeName . '" to "div".');
                    $nodeToAppend = $this->dom->createElement('div');

                    try {
                        $nodeToAppend->setAttribute('alt', $siblingNodeName);
                        $nodeToAppend->setInnerHtml($siblingNode->getInnerHTML());
                    } catch (\Exception $e) {
                        $this->logger->debug('Could not alter siblingNode "' . $siblingNodeName . '" to "div", reverting to original.');
                        $nodeToAppend = $siblingNode;
                        --$s;
                        --$sl;
                    }
                } else {
                    $nodeToAppend = $siblingNode;
                    --$s;
                    --$sl;
                }

                // To ensure a node does not interfere with readability styles, remove its classnames & ids.
                // Now done via RegExp post_filter.
                //$nodeToAppend->removeAttribute('class');
                //$nodeToAppend->removeAttribute('id');
                // Append sibling and subtract from our list as appending removes a node.
                $articleContent->appendChild($nodeToAppend);
            }
        }

        unset($xpath);

        // So we have all of the content that we need. Now we clean it up for presentation.
        $this->prepArticle($articleContent);

        /*
         * Now that we've gone through the full algorithm, check to see if we got any meaningful content.
         * If we didn't, we may need to re-run grabArticle with different flags set. This gives us a higher
         * likelihood of finding the content, and the sieve approach gives us a higher likelihood of
         * finding the -right- content.
         */
        if (mb_strlen($this->getInnerText($articleContent, false)) < self::MIN_ARTICLE_LENGTH) {
            $this->reinitBody();

            if ($this->flagIsActive(self::FLAG_STRIP_UNLIKELYS)) {
                $this->removeFlag(self::FLAG_STRIP_UNLIKELYS);
                $this->logger->debug('...content is shorter than ' . self::MIN_ARTICLE_LENGTH . " letters, trying not to strip unlikely content.\n");

                return $this->grabArticle($this->body);
            } elseif ($this->flagIsActive(self::FLAG_WEIGHT_ATTRIBUTES)) {
                $this->removeFlag(self::FLAG_WEIGHT_ATTRIBUTES);
                $this->logger->debug('...content is shorter than ' . self::MIN_ARTICLE_LENGTH . " letters, trying not to weight attributes.\n");

                return $this->grabArticle($this->body);
            } elseif ($this->flagIsActive(self::FLAG_CLEAN_CONDITIONALLY)) {
                $this->removeFlag(self::FLAG_CLEAN_CONDITIONALLY);
                $this->logger->debug('...content is shorter than ' . self::MIN_ARTICLE_LENGTH . " letters, trying not to clean at all.\n");

                return $this->grabArticle($this->body);
            }

            return false;
        }

        return $articleContent;
    }

    /**
     * Get an element weight by attribute.
     * Uses regular expressions to tell if this element looks good or bad.
     */
    protected function weightAttribute(DOMElement $element, string $attribute): int
    {
        if (!$element->hasAttribute($attribute)) {
            return 0;
        }
        $weight = 0;

        // $attributeValue = trim($element->getAttribute('class')." ".$element->getAttribute('id'));
        $attributeValue = trim($element->getAttribute($attribute));

        if ('' !== $attributeValue) {
            if (preg_match($this->regexps['negative'], $attributeValue)) {
                $weight -= 25;
            }
            if (preg_match($this->regexps['positive'], $attributeValue)) {
                $weight += 25;
            }
            if (preg_match($this->regexps['unlikelyCandidates'], $attributeValue)) {
                $weight -= 5;
            }
            if (preg_match($this->regexps['okMaybeItsACandidate'], $attributeValue)) {
                $weight += 5;
            }
        }

        return $weight;
    }

    /**
     * Will recreate previously deleted body property.
     */
    protected function reinitBody(): void
    {
        if (!isset($this->body->childNodes)) {
            $this->body = $this->dom->createElement('body');
            $this->body->setInnerHtml($this->bodyCache);
        }
    }

    /**
     * Load HTML in a DOMDocument.
     * Apply Pre filters
     * Cleanup HTML using Tidy (or not).
     */
    private function loadHtml(): void
    {
        $this->original_html = $this->html;

        $this->logger->debug('Parsing URL: ' . $this->url);

        if ($this->url) {
            $this->domainRegExp = '/' . strtr((string) preg_replace('/www\d*\./', '', (string) parse_url($this->url, \PHP_URL_HOST)), ['.' => '\.']) . '/';
        }

        mb_internal_encoding('UTF-8');
        mb_http_output('UTF-8');
        mb_regex_encoding('UTF-8');

        // HACK: dirty cleanup to replace some stuff; shouldn't use regexps with HTML but well...
        if (!$this->flagIsActive(self::FLAG_DISABLE_PREFILTER)) {
            foreach ($this->pre_filters as $search => $replace) {
                $this->html = preg_replace($search, $replace, $this->html);
            }
            unset($search, $replace);
        }

        if ('' === trim($this->html)) {
            $this->html = '<html></html>';
        }

        /*
         * Use tidy (if it exists).
         * This fixes problems with some sites which would otherwise trouble DOMDocument's HTML parsing.
         * Although sometimes it makes matters worse, which is why there is an option to disable it.
         */
        if ($this->useTidy) {
            $this->logger->debug('Tidying document');

            $tidy = tidy_repair_string($this->html, $this->tidy_config, 'UTF8');
            if (false !== $tidy && $this->html !== $tidy) {
                $this->tidied = true;
                $this->html = $tidy;
                $this->html = preg_replace('/[\r\n]+/is', "\n", $this->html);
            }
            unset($tidy);
        }

        $this->html = mb_convert_encoding((string) $this->html, 'HTML-ENTITIES', 'UTF-8');

        if ('html5lib' === $this->parser || 'html5' === $this->parser) {
            $this->dom = (new HTML5())->loadHTML($this->html);
        }

        if ('libxml' === $this->parser) {
            libxml_use_internal_errors(true);

            $this->dom = new \DOMDocument();
            $this->dom->preserveWhiteSpace = false;
            $this->dom->loadHTML($this->html, \LIBXML_NOBLANKS | \LIBXML_COMPACT | \LIBXML_NOERROR);

            libxml_use_internal_errors(false);
        }

        $this->dom->registerNodeClass(DOMElement::class, \Readability\JSLikeHTMLElement::class);
    }

    private function getAncestors(DOMElement $node, int $maxDepth = 0): array
    {
        $ancestors = [];
        $i = 0;
        while ($node->parentNode instanceof DOMElement) {
            $ancestors[] = $node->parentNode;
            if (++$i === $maxDepth) {
                break;
            }
            $node = $node->parentNode;
        }

        return $ancestors;
    }

    private function isPhrasingContent($node): bool
    {
        return \XML_TEXT_NODE === $node->nodeType
            || \in_array(strtoupper($node->nodeName), $this->phrasingElements, true)
            || (\in_array(strtoupper($node->nodeName), ['A', 'DEL', 'INS'], true) && !\in_array(false, array_map(function ($c) {
                return $this->isPhrasingContent($c);
            }, iterator_to_array($node->childNodes)), true));
    }

    private function hasSingleTagInsideElement(DOMElement $node, string $tag): bool
    {
        if (1 !== $node->childNodes->length || $node->childNodes->item(0)->nodeName !== $tag) {
            return false;
        }

        $a = array_filter(iterator_to_array($node->childNodes), function ($childNode) {
            return $childNode instanceof \DOMText &&
                preg_match($this->regexps['hasContent'], $this->getInnerText($childNode));
        });

        return 0 === \count($a);
    }

    /**
     * Return whether a given node is visible or not.
     *
     * Tidy must be configured to not clean the input for this function to
     * work as expected, see $this->tidy_config['clean']
     */
    private function isNodeVisible(DOMElement $node): bool
    {
        return !($node->hasAttribute('style')
                    && preg_match($this->regexps['isNotVisible'], $node->getAttribute('style'))
                )
                && !$node->hasAttribute('hidden');
    }
}
