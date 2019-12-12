# XPath Filter

* [xpath](#xpath-filter) - `"type":"xpath"`
	* [xpath](#xpath---xpathxpath-str---array-of-xpath-str-)  - `"xpath":"xpath str" / [ "array of xpath str" ]`
	* [index](#index---index-int) - `"index":int`

## Xpath Filter
The **xpath** value is the actual Xpath-element to fetch from the linked page. Omit the leading `//` - they will get prepended automatically.

See also - [Xpath General Information](#xpath-general-information)

### xpath - `"xpath":"xpath str" / [ "array of xpath str" ]`
Xpath string or Array of xpath strings

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

Xpaths are evaluated in the order they are given in the array and will be concatenated together. In the above example the output would be in the order of `Footer -> Content -> Header` instead of the normal `Header -> Footer -> Content`. See also [concatenation elements](#concatenation-elements)

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