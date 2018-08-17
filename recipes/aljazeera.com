{
    "name": "Al Jazeera",
    "url": "http://www.aljazeera.com",
    "match": "aljazeera.com",
    "author": "cwmke, updated by jreming85",
    "config": {
        "type": "xpath",
        "xpath": [
            "div[@class='article-heading']",
            "figure[@class='main-article-mediaCaption']",
            "div[@class='article-p-wrapper']"
        ],
        "cleanup": [
            "div[@class='article-readToMe-share-block']",
            "div[@class='article-more-on-block article-shaded-blocks article-embedded-card hidden-xs']"
        ]
    }
}
