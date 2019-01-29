{
    "name": "Wall Street Journal",
    "url": "http://www.wsj.com",
    "match": "wsj.com",
    "author": "jreming85",
    "config": {
        "type": "xpath",
        "xpath": [
        "div[@data-module-zone='article_snippet']"
        ],
        "cleanup": [
        "style",
        "div[@class='shareMenuInlineWrap']",
        "div[@class='snippet-actions']",
        "div[@class='wsj-snippet-related-video-wrap']",
        "div[@id='wsj-mobile-ad-target']",
        "div[@id='share-target']",
        "div[@data-layout='bigtophero']",
        "div[@class='wsj-snippet-login__stick-target']",
        "div[@class='paywall']",
        "div[@class='wsj-snippet-login']"
        ]
    }
}
