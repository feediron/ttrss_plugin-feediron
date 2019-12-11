{
    "name": "bleepingcomputer.com",
    "url": "bleepingcomputer.com",
    "author": "mcbyte-it",
    "match": "bleepingcomputer.com",
    "config": {
        "type": "xpath",
        "xpath": "div[@class='articleBody']",
        "cleanup": [
            "div[@class='cz-related-article-wrapp']"
        ]
    },
    "modify": [
        {
            "type": "replace",
            "search": [
                "src=\"data:image",
                "data-src="
            ],
            "replace": [
                "src-old=\"data:image",
                "src="
            ]
        }
    ]
}
