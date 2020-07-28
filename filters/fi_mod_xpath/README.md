## Xpath Filter
The **xpath** value is the actual Xpath-element to fetch from the linked page. Omit the leading `//` - they will get prepended automatically.

See also - [Xpath General Information](#xpath-general-information)

* XPath - `"type":"xpath"`
	* [xpath](#xpath---xpathxpath-str---array-of-xpath-str-)  - `"xpath":"xpath str" / [ "array of xpath str" ]`
	* [index](#index---index-int) - `"index":int`
	* [multipage](#multipage---multipageoptions) - `"multipage":{options}`
		* xpath - `"xpath":"xpath str"`
		* [append](#append---appendbool) - `"append":bool`
		* [recursive](#recursive---recursivebool) - `"recursive":bool`
	* [start_element](#start_element---start_elementstr) - `"start_element":"str"`
	* [join_element](#join_element---join_elementstr) - `"join_element":"str"`
	* [end_element](#end_element----end_elementstr) - `"end_element":"str"`
	* [cleanup](#cleanup---cleanupxpath-str---array-of-xpath-str-) - `"cleanup":"xpath str" / [ "array of xpath str" ]`
* [split](#split---typesplit) - `"type":"split"`
	* [steps](#steps---steps-array-of-steps-) - `"steps":[ array of steps ]`
      * after - `"after":"str"`
      * before - `"before":"str"`
	* [cleanup](#cleanup-cleanup-array-of-regex-) - `"cleanup":"/regex str/" / [ "/array of regex str/" ]`

### Basic Usage:

Xpaths are evaluated in the order they are given in the array and will be concatenated together. In the above example the output would be in the order of `Footer -> Content -> Header` instead of the normal `Header -> Footer -> Content`. See also [concatenation elements](#concatenation-elements)

Single xpath string:
```json
"example.com":{
  "type":"xpath",
  "xpath":"div[@id='content']"
}
```

Array of xpath strings:
```json
"example.com":{
  "type":"xpath",
  "xpath":[
    "div[@id='footer']",
    "div[@class='content']",
    "div[@class='header']",
  ]
}
```

### index - `"index": int`
Integer - Every xpath can also be an object consisting of an `xpath` element and an `index` element.

Selecting the 3rd Div in a page:
```json
"example.com":{
	"type":"xpath",
	"xpath":[
		{
			"xpath":"div",
			"index":3
		}
	]
}
```

### multipage - `"multipage":{[options]}`
This option indicates that the article is split into two or more pages (eventually). FeedIron can combine all the parts into the content of the article.

You have to specify a ```xpath``` which identifies the links (&lt;a&gt;) to the pages.

```json
"example.com":{
	"type": "xpath",
	"multipage": {
		"xpath": "a[contains(@data-ga-category,'Pagination') and text() = 'Next']",
		"append": true,
		"recursive": true
	}
}
```

#### append - `"append":bool`
Boolean - If false, only the links are used and the original link is ignored else the links found using the xpath expression are added to the original page link.

#### recursive - `"recursive":bool`
Boolean - If true this option to parses every following page for more links. To avoid infinite loops the fetching stops if an url is added twice.

### Concatenation Elements

#### start_element - `"start_element":"str"`
String - Prepends string to the start of content

```json
"example.com":{
  "type":"xpath",
  "xpath":[
    "div[@id='footer']"
  ],
  "start_element":"The Footer is >"
}
```
Result: `The Footer is ><p>Footer Text</p>`

#### join_element - `"join_element":"str"`
String - Joins xpath array content together with string

```json
"example.com":{
	"type":"xpath",
	"xpath":[
		"div[@id='footer']",
		"div[@class='header']"
	],
	"join_element":"<br><br>"
}
```

Result: `<p>Footer Text</p></div><br><br><p>Header Text</p>`

#### end_element  - `"end_element":"str"`
String - Appends string to the end of content

```json
"example.com":{
	"type":"xpath",
	"xpath":[
		"div[@class='header']"
	],
	"end_element":"< The Header was"
}
```

Result: `<p>Header Text</p>< The Header was`

**Full Example of Concatenation Elements:**

```json
"example.com":{
	"type":"xpath",
	"xpath":[
		"div[@id='footer']",
		"div[@class='content']",
		"div[@class='header']"
	],
	"start_element":"The Footer is >",
	"join_element":"<br><br>",
	"end_element":"< The Header was"
}
```

Result: `The Footer is ><p>Footer Text</p><br><br>><p>Content Text</p></div><br><br><p>Header Text</p>< The Header was`

### cleanup - `"cleanup":"xpath str" / [ "array of xpath str" ]`
An array of Xpath-elements (relative to the fetched node) to remove from the fetched node.

```json
"example.com":{
	"type":"xpath",
	"xpath":"div[@id='content']",
	"cleanup" : [ "~<script([^<]|<(?!/script))*</script>~msi" ]
}
```


## split - `"type":"split"`

### steps - `"steps":[ array of steps ]`
The steps value is an array of actions performed in the given order. If after is given the content will be split using the value and the second half is used, if before the first half is used. [preg_split](http://php.net/manual/en/function.preg-split.php) is used for this action.

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
]
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

## Xpath General Information
XPath is a query language for selecting nodes from an XML/html document.

<details>
<summary>Xpath Tools</summary>

To test your XPath expressions, you can use these Chrome extensions:

* [XPath Helper](https://chrome.google.com/webstore/detail/xpath-helper/hgimnogjllphhhkhlmebbmlgjoejdpjl)
* [xPath Viewer](https://chrome.google.com/webstore/detail/xpath-viewer/oemacabgcknpcikelclomjajcdpbilpf)
* [xpathOnClick](https://chrome.google.com/webstore/detail/xpathonclick/ikbfbhbdjpjnalaooidkdbgjknhghhbo)

</details>

### Xpath Examples

Some XPath expressions you could need (the `//` is automatically prepended and must be omitted in the FeedMod configuration):

#### HTML5 &lt;article&gt; tag
<details>

```html
<article>…article…</article>
```

```xslt
//article
```
</details>

#### DIV inside DIV
<details>

```html
<div id="content"><div class="box_content">…article…</div></div>`
```

```xslt
//div[@id='content']/div[@class='box_content']
```
</details>

#### Multiple classes
<details>

```html
<div class="post-body entry-content xh-highlight">…article…</div>
```

```xslt
//div[starts-with(@class ,'post-body')]
```
or
```xslt
//div[contains(@class, 'entry-content')]
```
</details>

#### Image tag
<details>

```html
<a><img src='test.png' /></a>
```
```xslt
img/..
```
</details>