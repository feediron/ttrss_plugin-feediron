{
    "name": "Bangkok Post",
    "url": "http://www.bangkokpost.com",
    "match": "bangkokpost.com",
    "author": "cwmke",
 	   "config": {
        "type": "xpath",
        "xpath": "div[@class='articleContents']",
        "cleanup": [
            "div[@class='text-size']",
            "div[@class='relate-story']",
            "div[@class='text-ads']",
            "script",
            "ul"
        ]
    }
}
