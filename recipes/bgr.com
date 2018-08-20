{
    "name": "BGR",
    "url": "http://www.bgr.com",
    "match": "bgr.com",
    "author": "jreming85",
    "config": {
        "type": "xpath",
        "xpath": [
            "h1[@class='entry-title']",
            "div[@class='entry-content']"
                 ],
        "cleanup": [
            "div[@class='dont-miss pink-gradient']",
            "div[@class='embed-twitter']",
            "div[@class='entry-tags']"
        ]
    }
}
