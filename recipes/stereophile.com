{
    "name": "stereophile",
    "url": "http://www.stereophile.com",
    "match": "stereophile.com",
    "author": "cwmke",
    "config": {
        "type": "xpath",
        "xpath": "div[@class='content clear-block']"
    }
}
