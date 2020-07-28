# Readability
The Readability modules are a automated method that attempts to isolate the relevant article text and images.

* readability - `"type":"readability"` Note: Also accepts all [Xpath type](#xpath-filter) options
	1. [PHP-Readability](#php-readability)
	2. [Readability.php](#readabilityphp) (Optionally installed)
		* [relativeurl](#relativeurl---relativeurlstr) - `"relativeurl":"str"`
		* [removebyline](#removebyline---removebylinebool) - `"removebyline":bool`
		* [normalize](#normalize---normalizebool) - `"normalize":bool`
		* [prependimage](#prependimage---prependimagebool) - `"prependimage":bool`
		* [mainimage](#mainimage---mainimagebool) - `"mainimage":bool`
		* [appendimages](#appendimages---appendimagesbool) - `"appendimages":bool`
		* [allimages](#allimages---allimagesbool) - `"allimages":bool`
	* [cleanup](#cleanup-cleanup-array-of-regex-) - `"cleanup": "/regex str/" / [ "/array of regex str/" ]`

Basic Usage:
```json
"example.com":{
	"type":"readability"
}
```

### PHP-Readability
In built default, This option makes use of [php-readability]( https://github.com/j0k3r/php-readability ) which is a fork of the [original](http://code.fivefilters.org/php-readability). All the extraction is performed within this module and has no configuration options

### Readability.php
Optionally installed via composer [Readability.php](https://github.com/andreskrey/readability.php) is a PHP port of Mozilla's Readability.js. All the extraction is performed within this module.

#### relativeurl - `"relativeurl":"str"`
Convert relative URLs to absolute. Like `/test` to `http://host/test`
```json
"example.com":{
	"type":"readability",
	"relativeurl":"http:\/\/example.com\/"
}
```

#### removebyline - `"removebyline":bool`
Default value `false`
```json
"example.com":{
	"type":"readability",
	"removebyline":true
}
```

#### normalize - `"normalize":bool`
Default value `false`

Converts UTF-8 characters to its HTML Entity equivalent. Useful to parse HTML with mixed encoding.
```json
"example.com":{
	"type":"readability",
	"normalize":true
}
```

#### prependimage - `"prependimage":bool`
Default value `false`

Returns the main image of the article Prepended before the article.
```json
"example.com":{
	"type":"readability",
	"prependimage":true
}
```

#### mainimage - `"mainimage":bool`
Default value `false`

Returns the main image of the article.
```json
"example.com":{
	"type":"readability",
	"mainimage":true
}
```

#### appendimages - `"appendimages":bool`
Default value `false`

Returns all images in article appended after the article.
```json
"example.com":{
	"type":"readability",
	"appendimages":true
}
```

#### allimages - `"allimages":bool`
Default value `false`

Returns all images in article without the article.
```json
"example.com":{
	"type":"readability",
	"allimages":true
}
```

### cleanup `"cleanup":[ "array of regex" ]`
Optional - An array of regex that are removed using preg_replace.

```json
"example.com":{
  "type":"split",
  "steps":[{
    "after": "/article-section clearfix\"\\W*>/",
    "before": "/<div\\W*class=\"module-box home-link-box/"
  },
  {
    "before": "/<div\\W*class=\"btwBarInArticles/"
  }
],
"cleanup" : [ "~<script([^<]|<(?!/script))*</script>~msi" ]
}
```