{
    "name": "TIME",
    "url": "http://time.com",
    "match": "time.com",
    "author": "cwmke",
    "config": {
        "type": "xpath",
        "xpath": "section[@class='article-body']",
        "cleanup": [
            "script",
            "span[contains(@class, 'read-video-article')]",
            "figure[@itemprop='video']",
            "a[contains(@href, 'time.com')]",
            "div[@class='content-ad']",
            "div[contains(@class, 'the-brief')]",
            "aside",
            "strong",
            "em",
            "iframe"
        ]
    }
}
