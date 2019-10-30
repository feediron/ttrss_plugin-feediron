{
    "name": "eetimes.com",
    "url": "eetimes.com",
    "match": "eetimes.com",
    "config": {
        "type": "xpath",
        "xpath": "div[@class='articleBody']",
        "multipage": {
            "xpath": "a[contains(@rel,'next')]",
            "append": true,
            "recursive": true
        }
    }
}
