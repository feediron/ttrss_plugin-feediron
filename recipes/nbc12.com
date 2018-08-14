{
    "name": "NBC12",
    "url": "http://www.nbc12.com",
    "match": "nbc12.com",
    "author": "jreming85",
    "config": {
        "type": "xpath",
		"xpath": [
            "*[@id='WNStoryRelatedBox']",
            "*[@id='WNStoryBody']"
        ]
    }
}
