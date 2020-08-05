# FeedIron TT-RSS Plugin <img src="icon.svg" width="80" align="left">
Reforge your feeds

**Recipes moved to separate [Repository](https://github.com/feediron/feediron-recipes)**

About |Table Of Contents
 :---- | --------
This is a plugin for [Tiny Tiny RSS (tt-rss)](https://tt-rss.org/).<br>It allows you to replace an article's contents by the contents of an element on the linked URL's page<br><br>i.e. create a "full feed".<br><br>Keep up to date by subscribing to the [Release Feed](https://github.com/feediron/ttrss_plugin-feediron/releases.atom)|<ul><li>[Installation](#installation)</li><li>[Configuration tab](#configuration-tab)</li><ul><li>[Usage](#usage)</li><li>[Filters](#filters)</li><li>[General Options](#general-options)</li><li>[Global Options](#global-options)</li></ul><li>[Testing Tab](#testing-tab)</li><li>[Full configuration example](#full-configuration-example)</li></ul>

## Installation

Checkout the directory into your plugins folder like this (from tt-RSS root directory):

```sh
$ cd /var/www/ttrss
$ git clone git://github.com/m42e/ttrss_plugin-feediron.git plugins.local/feediron
```

Then enable the plugin in TT-RSS preferences.

### Optional

Install [Readability.php](https://github.com/andreskrey/readability.php) using [composer](https://getcomposer.org/). Assuming composer is installed, navigate to the FeeIron plugin filter folder `filters/fi_mod_readability` with `composer.json` present and run:

```
$ composer install
```
___

# Layout

After install in the TinyTinyRSS preferences menu  you will find new tab called FeedIron. Under this tab you will have access to the FeedIron Configuration tab and the FeedIron Testing tab.

# Configuration tab
The configuration for FeedIron is done in [JSON format](https://json.org/) and will be displayed in the large configuration text field. Use the large field to enter/modify the configuration data and click the Save button to store it.

Additionally you can load predefined rules/recipes submitted by the community or export your own rules. To submit your own rules/recipes you can submit a pull request through Github in the [recipes repository](https://github.com/feediron/feediron-recipes).

![](./screenshots/config.png)

## Usage

There are [Filters](#filters), [general options](#general-options) and [global options](#global-options). Note: The rule `type` Must be defined and has to be one of the following: `xpath`, `split` or `readability`.

The best way to understand Feediron is to read the [Full configuration example](#full-configuration-example)

### Basic Configuration:

A Basic Configuration must define:

1. The site string. e.g. `example.com`
	* Use the same configuration for multiple URL's by seperating them with the `|` Delimiter. e.g. `"example.com|example.net"`
	* The configuration will be applied when the site string matches the `<link>` or `<author>` tag of the RSS feed item.
		* The `<link>` takes precedence over the `<author>`
		* `<author>` based configurations will **NOT** automatically show in the Testing Tab
2. The Filter type. e.g. `"type":"xpath"`
3. The Filter config. e.g. `"xpath":"div[@id='content']"` or the array `"xpath": [ "div[@id='article']", "div[@id='footer']"]`


Example:
```json
{
  "example.com":{
    "type":"xpath",
    "xpath":"div[@id='content']"
  },
  "secondexample.com":{
    "type":"xpath",
    "xpath": [
      "div[@id='article']",
      "div[@id='footer']"
    ]
  }
}
```
<sub>Note: Take care while values are separated by a `,` (comma) using a trailing `,` (comma) is not valid.</sub>

---

# Filters:
* [XPath Module](https://github.com/feediron/ttrss_plugin-feediron/tree/master/filters/fi_mod_xpath#readme) - `"type":"xpath"`
* [Split Module](https://github.com/feediron/ttrss_plugin-feediron/tree/master/filters/fi_mod_split#readme) - `type":"split"`
* [Readability Module](https://github.com/feediron/ttrss_plugin-feediron/tree/master/filters/fi_mod_readability#readme) - `"type":"readability"`
* [tags](#tags-filter) - `"tags":"{options}"`
	* [xpath](#tags-type-xpath---type-xpath) - `"type": "xpath"`
		* [xpath](#tags-xpath---xpathxpath-str---array-of-xpath-str-) - `"xpath":"xpath str" / [ "array of xpath str" ]`
	* [regex](#tags-type-regex---type-regex) - `"type": "regex"`
		* [pattern](#tags-regex-pattern---pattern-regex-str---array-of-regex-str-) - `"pattern": "/regex str/" / [ "/array of regex str/" ]`
		* [index](#tags-regex-index---indexint) - `"index":int`
	* [search](#tags-type-search---type-search) - `"type": "search"`
		* [pattern](#tags-search-pattern---pattern-regex-str---array-of-regex-str-) - `"pattern": "/regex str/" / [ "/array of regex str/" ]`
		* [match](#tags-search-match---match-str---array-of-str-) - `"match": "str" / [ "array of str" ]`
	* [replace-tags](#replace-tags---replace-tagsbool) - `"replace-tags":bool`
	* [cleanup](#cleanup---cleanupxpath-str---array-of-xpath-str-) - `"cleanup":"xpath str" / [ "array of xpath str" ]`
	* [split](#split---splitstr) - `"split":"str"`

## Tags Filter
FeedIron can fetch text from a page and save them as article tags. This can be used to improve the filtering options found in TT-RSS. Note: The Tag filter can use all the options available to the xpath filter and the modify option.

The order of execution for tags is:

 1. Fetch Tag HTML.
 2. Perform Cleanup tags individually.
 3. Split Tags.
 4. Modify Tags individually.
 5. Strip any remaining HTML from Tags.

Usage Example:
```json
"tags": {
    "type": "xpath",
    "replace-tags":true,
    "xpath": [
        "p[@class='topics']"
    ],
    "split":",",
    "cleanup": [
        "strong"
    ],
    "modify":[
      {
        "type": "replace",
        "search": "-",
        "replace": " "
      }
    ]
}
```
### tags type xpath - `"type": "xpath"`

#### tags xpath - `"xpath":"xpath str" / [ "array of xpath str" ]`

```json
"tags":{
	"type":"xpath",
  "xpath":"p[@class='topics']"
}
```

### tags type regex - `"type": "regex"`

Uses PHP preg_match() in order to find and return a string from the article. Requires at least on pattern.

#### tags regex pattern - `"pattern": "/regex str/" / [ "/array of regex str/" ]`

```json
"tags":{
	"type":"regex",
  "pattern": "/The quick.*fox jumped/"
}
```

#### tags regex index - `"index":int`

Specifies the number of the entry in article to return.
Default value `1`

```json
"tags":{
	"type":"regex",
  "pattern": "/The quick.*fox jumped/",
  "index": 2
}
```

### tags type search - `"type": "search"`

Search article using regex, if found it returns a pre-defined matching tag.

```json
"tags":{
	"type":"search",
  "pattern": [
    "/feediron/",
    "/ttrss/"
  ],
  "match": [
    "FeedIron is here",
    "TT-RSS is here"
  ]
}
```

#### tags search pattern - `"pattern": "/regex str/" / [ "/array of regex str/" ]`

Must have corresponding match entries

#### tags search match - `"match": "str" / [ "array of str" ]`

Must have corresponding pattern entries. This can be inverted using the `!` symbol at the beginning of the match entry to return if **NO** match is found

```json
"tags":{
	"type":"search",
  "pattern": [
    "/feediron/",
    "/ttrss/"
  ],
  "match": [
    "!FeedIron is not here",
    "TT-RSS is here"
  ]
}
```

### replace-tags - `"replace-tags":bool`
Default value `false`

Replace the article tags with fetched ones. By default tags are merged.

```json
"tags":{
	"type":"xpath",
  "xpath":"p[@class='topics']",
  "replace-tags": true
}
```

## split - `"split":"str"`
String - Splits tags using a delimiter
```json
"tags":{
	"type":"xpath",
  "xpath":"p[@class='topics']",
  "split":"-"
}
```
Input: `Tag1-Tag2-Tag3`

Result: `Tag1, Tag2, Tag3`

---

# General Options:

* [reformat / modify](#reformat--modify---reformatarray-of-options-modifyarray-of-options) - `"reformat":[array of options]` `"modify":[array of options]`
	* [regex](#regex---typeregex) - `"type":"regex"`
		* [pattern](#pattern---patternregex-str)  - `"pattern":"/regex str/"`
		* [replace](#replace---replacestr) - `"replace":"str"`
	* [replace](#replace---typereplace) - `"type":"replace"`
		* [search](#search---typesearch-str---array-of-search-str-) - `"type":"search str" / [ "array of search str" ]`
		* [replace](#replace---replacestr---array-of-str-) - `"replace":"str"`
* [force_charset](#force_charset---force_charsetcharset) - `"force_charset":"charset"`
* [force_unicode](#force_unicode---force_unicodebool) - `"force_unicode":bool`
* [tidy-source](#tidy-source---tidy-sourcebool) - `"tidy-source":bool`
* [tidy](#tidy---tidybool) - `"tidy":bool`

## reformat / modify - `"reformat":[array of options]` `"modify":[array of options]`

Reformat is an array of formatting rules for the url of the full article. The rules are applied before the full article is fetched. Where as Modify is an array of formatting rules for article using the same options.

### regex - `"type":"regex"`

regex takes a regex in an option called pattern and the replacement in replace. For details see [preg_replace](http://www.php.net/manual/de/function.preg-replace.php) in the PHP documentation.

#### pattern - `"pattern":"/regex str/"`

A regular expression or regex string.

#### replace - `"replace":"str"`

String to replace regex match with

Example reformat golem.de url:

```json
"golem0Bde0C":{
  "type":"xpath",
  "xpath":"article",
  "reformat": [
    {
      "type": "regex",
      "pattern": "/(?:[a-z0-9A-Z\\/.\\:]*?)golem0Bde0C(.*)0Erss0Bhtml\\/story01.htm/",
      "replace": "http://www.golem.de/$1.html"
    }
  ]
}
```

### replace - `"type":"replace"`

Uses the PHP function [str_replace](http://php.net/manual/en/function.str-replace.php), which takes either a string or an array as search and replace value.

#### search - `"type":"search str" / [ "array of search str" ]`

String to search for replacement. If an array the order will match the replacement string order

#### replace - `"replace":"str" / [ "array of str" ]`

String to replace search match with. Array must have the same number of options as the search array.

Example search and replace instances of srcset with null:

```json
{
  "type": "xpath",
  "xpath": "img",
  "modify": [
    {
      "type": "replace",
      "search": "srcset",
      "replace": "null"
    }
  ]
}
```

Example search and replace h1 and h2 tags with h3 tags:

```json
"example.tld":{
  "type": "xpath",
  "xpath": "article",
  "modify": [
    {
      "type": "replace",
      "search": [
        "<h1>",
        "<\/h1>",
        "<h2>",
        "<\/h2>"
      ],
      "replace": [
        "<h3>",
        "<\/h3>",
        "<h3>",
        "<\/h3>"
      ]
    }
  ]
}
```

### force_charset - `"force_charset":"charset"`

force_charset allows to override automatic charset detection. If it is omitted, the charset will be parsed from the HTTP headers or loadHTML() will decide on its own.

```json
"example.tld":{
  "type": "xpath",
  "xpath": "article",
  "force_charset": "utf-8"
}
```

### force_unicode - `"force_unicode":bool`

force_unicode performs a UTF-8 character set conversion on the html via [iconv](http://php.net/manual/en/function.iconv.php).

```json
"example.tld":{
  "type": "xpath",
  "xpath": "article",
  "force_unicode": true
}
```
### tidy-source - `"tidy-source":bool`

Optionally installed php-tidy. Default - `false`

Use [tidy::cleanrepair](https://secure.php.net/manual/en/tidy.cleanrepair.php) to attempt to fix fetched article source, useful for improperly closed tags interfering with xpath queries.

Note: If Character set of page cannot be detected tidy will not be executed. In this case usage of [force_charset](#force_charset---force_charsetcharset) would be required.

### tidy - `"tidy":bool`

Optionally installed php-tidy. Default - `true`

Use [tidy::cleanrepair](https://secure.php.net/manual/en/tidy.cleanrepair.php) to attempt to fix modified article, useful for unclosed tags such as iframes.

Note: If Character set of page cannot be detected tidy will not be executed. In this case usage of [force_charset](#force_charset---force_charsetcharset) would be required.

---

# Global options

### debug - `"debug":bool`

Activate debugging information (Note: not for testing tab).  Default - `false`

At the moment there is not that much debug information to be activated, this option must be places at the same level as the site configs.

Example:

```json
{
  "example.com":{
    "type":"xpath",
    "xpath":"div[@id='content']"
  },
  "secondexample.com":{
    "type":"xpath",
    "xpath": [
      "div[@id='article']",
      "div[@id='footer']"
    ]
  },
  "debug":false
}
```

### tidy-source - `"tidy-source":bool`

Allows you to disable globally the use of php-tidy on the fetched html source. tidy-source. Default - `true`

Uses tidy::cleanrepair to attempt to fix fetched article source, useful for improperly closed tags interfering with xpath queries.

Example:

```json
{
  "example.com":{
    "type":"xpath",
    "xpath":"div[@id='content']"
  },
  "secondexample.com":{
    "type":"xpath",
    "xpath": [
      "div[@id='article']",
      "div[@id='footer']"
    ]
  },
  "tidy-source":false
}
```

---

# Testing tab
The Testing tab is where you can debug/create your configurations and view a preview of the filter results. The configuration in the testing tab is identical to the configuration tab while omitting the domain/url.

```json
{
  "type":"xpath",
  "xpath":"article"
}
```

Not

```json
"example.tld":{
  "type":"xpath",
  "xpath":"article"
}
```

![](./screenshots/testing.png)


# Full configuration example

```json
{

  "heise.de": {
    "name": "Heise Newsticker",
    "url": "http://heise.de/ticker/",
    "type": "xpath",
    "xpath": "div[@class='meldung_wrapper']",
    "force_charset": "utf-8"
  },
  "berlin.de/polizei": {
    "type": "xpath",
    "xpath": "div[@class='bacontent']"
  },
  "n24.de": {
    "type": "readability",
  },
  "www.dorkly.com": {
    "type": "xpath",
    "multipage": {
      "xpath": "a[contains(@data-ga-category,'Pagination') and text() = 'Next']",
      "append": true,
      "recursive": true
    },
    "xpath": "div[contains(@class,'post-content')]"
  },
  "golem0Bde0C": {
    "type": "xpath",
    "xpath": "article",
    "multipage": {
      "xpath": "ol/li/a[contains(@id, 'atoc_')]",
      "append": true
    },
    "reformat": [
      {
        "type": "regex",
        "pattern": "/(?:[a-z0-9A-Z\\/.\\:]*?)golem0Bde0C(.*)0Erss0Bhtml\\/story01.htm/",
        "replace": "http://www.golem.de/$1.html"
      },
      {
        "type": "replace",
        "search": [
          "0A",
          "0C",
          "0B",
          "0E"
        ],
        "replace": [
          "0",
          "/",
          ".",
          "-"
        ]
      }
    ]
  },
  "oatmeal": {
    "type": "xpath",
    "xpath": "div[@id='comic']"
  },
  "blog.beetlebum.de": {
    "type": "xpath",
    "xpath": "div[@class='entry-content']",
    "cleanup": [ "header", "footer" ]
  },
  "sueddeutsche.de": {
    "type": "xpath",
    "xpath": [
      "h2/strong",
      "section[contains(@class,'authors')]"
    ],
    "join_element": "<p>",
    "cleanup": [
      "script"
    ]
  },
  "www.spiegel.de": {
    "type": "split",
    "steps": [
      {
        "after": "/article-section clearfix\"\\W*>/",
        "before": "/<div\\W*class=\"module-box home-link-box/"
      },
      {
        "before": "/<div\\W*class=\"btwBarInArticles/"
      }
    ],
    "cleanup" : [ "~<script([^<]|<(?!/script))*</script>~msi" ],
    "force_unicode": true
  },
  "debug": false

}
```

## Special Thanks
Thanks to [mbirth](https://github.com/mbirth) who wrote [af_feedmod](https://github.com/mbirth/ttrss_plugin-af_feedmod) who gave me a starting base.
