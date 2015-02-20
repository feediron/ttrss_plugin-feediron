{
    "name": "www.dorkly.com",
    "url": "www.dorkly.com",
    "stamp": 1424411024,
    "author": "Matthias Bilger",
    "match": "www.dorkly.com",
    "config": {
        "type": "xpath",
        "multipage": {
            "xpath": "a[contains(@data-ga-category,'Pagination') and text() = 'Next']",
            "append": true,
            "recursive": true
        },
        "xpath": "div[contains(@class,'post-content')]"
    }
}
