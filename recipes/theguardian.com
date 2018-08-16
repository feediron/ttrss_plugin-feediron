{
    "name": "The Guardian",
    "url": "https://www.theguardian.com",
    "match": "theguardian.com",
    "author": "jreming85",
    "config": {
            "type": "xpath",
            "xpath": [
            "a[@class='article__img-container js-gallerythumbs']",
            "div[@class='content__article-body from-content-api js-article__body']"
        ],
        "cleanup": [
            "span[@class='inline-expand-image inline-icon centered-icon rounded-icon article__fullscreen modern-visible']",
            "div[@class='rich-link tone-editorial--item rich-link--pillar-opinion']",
            "div[@class='rich-link tone-dead--item rich-link--pillar-news']",
            "div[@id='dfp-ad--inline3']",
            "span[@class='inline-arrow-in-circle inline-icon']",
            "div[@class='rich-link__read-more-text']",
            "div[@class='rich-link__arrow']",
            "div[@class='after-article js-after-article']",
            "div[@class='submeta']",
            "div[@data-component='share']",
            "div[@id='dfp-ad--inline1'"
        ]
    }
}
