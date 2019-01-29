{
    "name": "Associated Press",
    "url": "http://www.apnews.com",
    "match": "apnews.com",
    "author": "jreming85",
 	   "config": {
        "type": "xpath",
        "xpath": [
            "div[@class='dtTitleContainer']",
            "img[@class='afkl-lazy-image primaryImage']",
            "div[@class='articleBody']"
        ],
        "cleanup": [
            "style",
            "div[@class='ad-placeholder']",
            "script",
            "meta",
            "div[@class='ad-placeholder']",
            "div[@id='imageModal']",
            "div[@class='modal-body']",
            "div[@class='leftRailTags']",
            "div[@id='outerShareContainer']"
        ]
    }
}
