{
    "name": "Fox News",
    "url": "http://www.foxnews.com",
    "match": "foxnews.com",
    "author": "jreming85",
    "config": {
        "type": "xpath",
        "xpath": [
            "div[@class='article-body']"
        ],
        "cleanup": [
            "div[@class='ad-container tablet']",
            "div[@class='ad-container mobile']",
            "div[@id='ad-inread-1x1']",
            "div[@class='article-meta']",
            "div[@class='article-footer']"
        ]
    }
}
