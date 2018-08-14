{
    "name": "fredericksburg.com",
    "url": "http://www.fredericksburg.com",
    "match": "fredericksburg.com",
    "author": "jreming85",
    "config": {
        "type": "xpath",
		"xpath": "div[@class='main-content-wrap']",
        "cleanup": [
            "div[@id='tncms-region-article_top']",
            "div[@id='tncms-region-article_top_content'",
            "div[@class='tncms-region hidden-print']",
            "div[@class='share-container content-above'",
            "div[@data-subscription-required-remove='']",
            "div[@class='expand hidden-print'",
            "style",
            "div[@id='tncms-region-article_body_top']",
            "div[@id='tncms-region-article_instory_top'",
            "div[@class='share-container content-below'",
            "div[@id='asset-below']"
        ]
    }
}
