{
    "name": "arstechnica.com",
    "url": "arstechnica.com",
    "stamp": 1470889961,
    "author": "cwmke",
    "match": "arstechnica.com",
    "config": {
        "type": "xpath",
        "xpath": "div[contains(@class, 'article-content')]",
        "multipage": {
            "xpath": "nav[contains(@class, 'page-numbers')]\/span\/a[last()]",
            "append": true,
            "recursive": true
        },
        "modify": [
            {
                "type": "regex",
                "pattern": "\/<li.*? data-src=\"(.*?)\".*?>\\s*<figure.*?>.*?(?:<figcaption.*?<div class=\"caption\">(.*?)<\\\/div>.*?<\\\/figcaption>)?\\s*<\\\/figure>\\s*<\\\/li>\/s",
                "replace": "<figure><img src=\"$1\"\/><figcaption>$2<\/figcaption><\/figure>"
            }
        ],
        "cleanup": [
            "aside",
            "div[contains(@class, 'sidebar')]"
        ]
    }
}
