
{
    "name": "thehill.com",
    "url": "http://www.thehill.com",
    "match": "thehill.com",
    "author": "jreming85",
    "config": {
        "type": "xpath",
		"xpath": "div[@class='content-wrp']",
    "cleanup": "div[@class='dfp-tag-wrapper']"
    }
}
