{
    "name": "smashingmagazine.com",
    "url": "smashingmagazine.com",
    "stamp": 1467307920,
    "author": "Nguyễn Đình Quân",
    "match": "smashingmagazine.com",
    "config": {
        "type": "xpath",
        "xpath": "article[contains(@class,'status-publish')]",
        "cleanup": [
            "ul[contains(@class,'pmd')]",
            "div[contains(@class,'ad ed')]",
            "div[contains(@id,'editors-note')]",
            "div[contains(@class,'lt')]",
            "a[contains(@class,'sot')]"
        ]
    }
}
