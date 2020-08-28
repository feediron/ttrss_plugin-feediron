# Readability Filter

The Readability modules are a automated method that attempts to isolate the relevant article text and images.

Note: Also accepts all [Xpath type](https://github.com/feediron/ttrss_plugin-feediron/tree/master/filters/fi_mod_xpath) options

### Optional Readability.php

Install [Readability.php](https://github.com/andreskrey/readability.php) using [composer](https://getcomposer.org/). Assuming composer is installed, navigate to the FeeIron plugin filter folder `filters/fi_mod_readability` with `composer.json` present and run: `composer install`

* [Readability](#readability) - `"type":"readability"`
	1. [PHP-Readability](#php-readability)
	2. [Readability.php](#readabilityphp) (Optionally installed)
		* [relativeurl](#relativeurl---relativeurlstr) - `"relativeurl":"str"`
		* [removebyline](#removebyline---removebylinebool) - `"removebyline":bool`
		* [normalize](#normalize---normalizebool) - `"normalize":bool`
		* [excerpt](#excerpt---excerptbool) - `"excerpt":bool`
		* [mainimage](#mainimage---mainimagebool) - `"mainimage":bool`
		* [allimages](#allimages---allimagesbool) - `"allimages":bool`
		* [prependexcerpt](#prependexcerpt---prependexcerptbool) - `"prependexcerpt":bool`
		* [prependimage](#prependimage---prependimagebool) - `"prependimage":bool`
		* [appendimages](#appendimages---appendimagesbool) - `"appendimages":bool`
	* [cleanup](#cleanup-cleanup-array-of-regex-) - `"cleanup": "/regex str/" / [ "/array of regex str/" ]`

### Basic Usage:
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

#### excerpt - `"excerpt":bool`
Default value `false`

Returns an excerpt of the article as the content.
```json
"example.com":{
	"type":"readability",
	"excerpt":true
}
```

#### mainimage - `"mainimage":bool`
Default value `false`

Returns the main image of the article as the content.
```json
"example.com":{
	"type":"readability",
	"mainimage":true
}
```

#### allimages - `"allimages":bool`
Default value `false`

Returns all images in article without the article as the content.
```json
"example.com":{
	"type":"readability",
	"allimages":true
}
```

#### prependexcerpt - `"prependexcerpt":bool`
Default value `false`

Returns an excerpt of the article Prepended before the content.
```json
"example.com":{
	"type":"readability",
	"prependexcerpt":true
}
```

#### prependimage - `"prependimage":bool`
Default value `false`

Returns the main image of the article Prepended before the content.
```json
"example.com":{
	"type":"readability",
	"prependimage":true
}
```

#### appendimages - `"appendimages":bool`
Default value `false`

Returns all images in article appended after the content.
```json
"example.com":{
	"type":"readability",
	"appendimages":true
}
```

### cleanup `"cleanup":[ "array of regex" ]`
Optional - An array of regex that are removed using preg_replace.

```json
"example.com":{
  "type":"readability",
	"cleanup" : [ "/<script>.*?</script>/" ]
}
```
