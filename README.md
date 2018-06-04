Wordpress Facebook Squirrel
====

This Wordpress plugin fetches Facebook wall feeds' data from Facebook pages and stores them in the database. It also allows you to resize images from both: Facebook and external sources.

**Current features**
- Adding and managing multiple feeds per site
- Creating shortcodes for each feed
- Resizing images embedded in Facebook posts
- Resizing images added to posts by re-fetching them form the original source
- Limiting number of posts to be fetched and displayed on the site
- Hourly auto-update
 

**Installation**

1. Download the package and unpack it into your Wordpress installation directory under
`wp-content/plugins/`
2. Activate the plugin in Plugins section
3. Go to https://developers.facebook.com/apps and create a new application
4. Go back to your Wordpress back-end, and to Setting => Facebook Squirrel and fill in `Facebook APP ID` and `Application secret` by copying the data from the application you have just created.
5. Save settings
 
**Adding a new feed**

In order to add a new feed you need to know its ID. Page IDs are provided by Facebook Graph. To obtain page's ID follow the following steps:

**Manually:**

1. Visit a Facebook page of your choice
2. Copy it's name. For example, from `https://www.facebook.com/Batman` the name would be `Batman`
3. Navigate to https://graph.facebook.com/PAGE_NAME. In our example: https://graph.facebook.com/Batman
4. In the output find: `"id": "6939574006"`
5. Copy that ID to appropriate field in the plugin page, fill in the fetch limit (and optionally image sizes) and press Create new.

**Automatically, using findmyfbid.com**
- Just visit the site http://findmyfbid.in and provide it with URL of page you wish to get ID of

