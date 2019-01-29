    {
    "name": "Naked Security by Sophos ",
    "url": "http://nakedsecurity.sophos.com",
    "match": "nakedsecurity.sophos.com",
    "author": "jreming85",
 	   "config": {
        "type": "xpath",
        "xpath": [
            "header[@class='entry-header']",
            "div[@class='entry-featured-image']",
            "div[@class='entry-content']"
        ],
        "join_element": "<br><br>",
        "cleanup": [
            "nav[@class='navigation post-navigation']",
            "div[@id='newsletter-signup']",
            "div[@class='entry-sharing']",
            "div[@class='free-tools-block']",
            "iframe[@class='twitter-follow-button twitter-follow-button-rendered']",
            "div[@class='entry-tags']"
        ]
    }
}
