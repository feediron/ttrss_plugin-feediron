{
    "name": "Threatpost Security Blog",
    "url": "http://threatpost.com",
    "match": "threatpost.com",
    "author": "jreming85",
 	   "config": {
        "type": "xpath",
        "xpath": [
            "h1[@class='c-article__title']",
            "div[@class='c-article__main']"
        ],
        "cleanup": [
            "footer[@class='c-article__footer']"
        ]
    }
}
