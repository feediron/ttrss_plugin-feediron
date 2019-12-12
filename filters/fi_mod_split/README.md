# Split Filter

* [split](#split---typesplit) - `"type":"split"`
	* [steps](#steps---steps-array-of-steps-) - `"steps":[ array of steps ]`
      * after - `"after":"str"`
      * before - `"before":"str"`
	* [cleanup](#cleanup-cleanup-array-of-regex-) - `"cleanup":"/regex str/" / [ "/array of regex str/" ]`

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