{
    "name": "wonderhowto.com",
    "url": "wonderhowto.com",
    "stamp": 1448989166,
    "author": "Nguy\u1ec5n \u0110\u00ecnh Qu\u00e2n",
    "match": "wonderhowto.com",
    "config": {
        "type": "xpath",
        "xpath": "div[@class='article-container']",
        "cleanup": [
            "iframe",
            "header",
            "footer",
            "div[contains(@class, 'sharedaddy')]",
            "section[@class='see-also-container']"
        ]
    }
}
