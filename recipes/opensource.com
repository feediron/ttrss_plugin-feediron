{
    "name": "opensource.com",
    "url": "http://www.opensource.com",
    "match": "opensource.com",
    "author": "cwmke",
    "config": {
        "type": "xpath",
        "xpath": [
            "img[@class='image-full-size']",
            "div[contains(@class, 'field-type-text-with-summary')]"
        ]
    }
}
