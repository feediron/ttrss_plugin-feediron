{
    "name": "BBC",
    "url": "http://www.bbc.com",
    "match": "bbc.com",
    "author": "jreming85",
    "config": {
        "type": "xpath",
        "xpath": [
        "div[@class='story-body__inner']"
        ],
        "cleanup": [
        "form",
        "div[@class='bbccom_advert']",
        "script",
        "div[@id='topic-tags']",
        "div[@class='share share--lightweight  show ghost-column']",
        "div[@id='story-more']",
        "div[@class='callout-box']"
        ]
    }
}
