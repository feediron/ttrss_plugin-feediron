{
    "name": "Associated Press",
    "url": "http://www.secureworks.com",
    "match": "secureworks.com",
    "author": "jreming85",
 	   "config": {
        "type": "xpath",
        "xpath": [
            "article[@id='content']"
        ],
        "cleanup": [
            "ul[@class='social-networks']"
        ]
    }
}
