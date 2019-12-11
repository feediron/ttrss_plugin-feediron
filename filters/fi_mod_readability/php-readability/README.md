PHP Readability
================

This is a PHP port of Arc90's original Javascript version of Readability. (Arc90 has since relaunched the project.)

For instructions on how to use this, please see <http://www.keyvan.net/2010/08/php-readability/>

For a more flexible and robust solution to article extraction, take a look at [Full-Text RSS](http://fivefilters.org/content-only/) - it makes use of PHP Readability, but offers much more.

Feel free to fork this and change/improve it. I would love to see your results. Please do share them and I'll consider pulling them in.

PHP Readability is licensed under the Apache License, Version 2.0 (the same license as the original JS version). The original Javascript version can be found here: <http://code.google.com/p/arc90labs-readability/source/browse/> (readability.js)

### Donate

If you find this useful, please consider purchasing [Full-Text RSS](http://fivefilters.org/content-only/) or donating via [Gittip](https://www.gittip.com/fivefilters/)

### Simple example

	<?php
	require_once 'readability/Readability.php';
	header('Content-Type: text/plain; charset=utf-8');

	// get latest Medialens alert 
	// (change this URL to whatever you'd like to test)
	$url = 'http://www.medialens.org/index.php/alerts/alert-archive/alerts-2013/729-thatcher.html';
	$html = file_get_contents($url);

	// Note: PHP Readability expects UTF-8 encoded content.
	// If your content is not UTF-8 encoded, convert it 
	// first before passing it to PHP Readability. 
	// Both iconv() and mb_convert_encoding() can do this.

	// If we've got Tidy, let's clean up input.
	// This step is highly recommended - PHP's default HTML parser
	// often doesn't do a great job and results in strange output.
	if (function_exists('tidy_parse_string')) {
		$tidy = tidy_parse_string($html, array(), 'UTF8');
		$tidy->cleanRepair();
		$html = $tidy->value;
	}

	// give it to Readability
	$readability = new Readability($html, $url);
	// print debug output? 
	// useful to compare against Arc90's original JS version - 
	// simply click the bookmarklet with FireBug's console window open
	$readability->debug = false;
	// convert links to footnotes?
	$readability->convertLinksToFootnotes = true;
	// process it
	$result = $readability->init();
	// does it look like we found what we wanted?
	if ($result) {
		echo "== Title =====================================\n";
		echo $readability->getTitle()->textContent, "\n\n";
		echo "== Body ======================================\n";
		$content = $readability->getContent()->innerHTML;
		// if we've got Tidy, let's clean it up for output
		if (function_exists('tidy_parse_string')) {
			$tidy = tidy_parse_string($content, array('indent'=>true, 'show-body-only' => true), 'UTF8');
			$tidy->cleanRepair();
			$content = $tidy->value;
		}
		echo $content;
	} else {
		echo 'Looks like we couldn\'t find the content. :(';
	}