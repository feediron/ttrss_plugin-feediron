{
    "name": "Breitbart",
    "url": "http://www.breitbart.com",
    "match": "breitbart.com",
    "author": "jreming85",
    "config": {
        "type": "xpath",
		"xpath": "section[@id='MainW']",
        "cleanup": [
            "footer",
            "div[@id='rev-content-bottom-main-column-mix']",
            "div[@id='cin_ps_in_article']",
            "section[@id='comments']",
            "script"
        ]
    }
}
