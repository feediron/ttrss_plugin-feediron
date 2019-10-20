{
    "name": "hackaday.com",
    "url": "hackaday.com",
    "match": "hackaday.com",
    "config": {
        "type": "xpath",
        "xpath": "article",
        "cleanup": [
            "ul[@class='sharing']",
            "ul[@class='share-post']"
        ]
    }
}
