
{
    "name": "Yahoo",
    "url": "http://www.yahoo.com",
    "match": "yahoo.com",
    "author": "jreming85",
    "config": {
        "type": "xpath",
        "xpath": [
             "article[@itemprop='articleBody']"
        ]
    }
}
