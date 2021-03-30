=== Ahrefs SEO ===
Contributors: justinahrefs
Plugin link: https://ahrefs.com/wordpress-seo-plugin
Tags: seo, content analysis, google analytics, google search console
Requires at least: 5.0
Tested up to: 5.7
Stable tag: 0.7.5
Requires PHP: 5.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Increase your organic traffic: Audit your content performance, improve your content quality & get more organic search traffic with the Ahrefs SEO plugin.

== Description ==

**Looking for a lightweight SEO plugin to improve your site's search engine rankings?** The Ahrefs SEO WordPress plugin helps you perform content audits so you can get more organic traffic to your website.

Getting organic traffic to your site is hard. The most prolific content creators have discovered that to win at the SEO game, you need to commit to publishing <a href="https://ahrefs.com/blog/seo-content/" target="_blank">in-depth content regularly.</a> While publishing frequently is helpful for getting high search engine rankings, doing it without a concrete strategy can result in a bloated and under-optimized website.

Knowing your website's <a href="https://ahrefs.com/blog/what-are-backlinks/" target="_blank">backlink profile is also an essential part of securing high search rankings.</a> Understanding which sites are linking to your content helps you better plan your content promotion strategy.

**The best marketers know that dedicating resources to improve their content and acquire backlinks generate massive rewards.** Unfortunately, not everyone has the expertise to analyze a complex set of data to perform these simple yet important tasks.

After years of building SEO tools for some of the top search marketing professionals, we are giving regular WordPress sites the ability to tap into these resources and expertise so you can start winning at the SEO game too.

**Unique to Ahrefs SEO plugin**

There are a lot of other SEO plugins in the market... So you must be thinking, why another one? First of all, the Ahrefs SEO plugin is complimentary to the other SEO plugins you are using. We're focusing on features unique to Ahrefs:

* **Backlink Index**
Ahrefs boasts the world's largest index of live backlinks. We update it with fresh data every 15-30 minutes. We use backlink data to calculate our proprietary metrics: Ahrefs Rank, Domain Rating and URL Rating.

* **Google Analytics Integration**
Unlike other plugins, we don't just pull data into your WordPress Admin and show you the same information that you can see on your Google Analytics dashboard. We analyze your traffic and conversion data to give you an idea of what to do next so you can take actionable steps towards getting more search traffic.

* **Google Search Console Integration**
We utilize your Google Search Console connection to provide you with better target keyword recommendations for your posts. We analyze your keywords data to give you suggestions on how to redirect or merge your content.

* **Content Audit**
We combine data from our backlink index together with your Google Analytics & Search Console account to provide you targeted recommendations on how to improve your content so it gets more search traffic.

**And the usual best-practices**

Other than the unique features, we make sure that we adhere to the best-practices for WordPress plugin development.

* **Lightning fast**
The plugin puts a minimal load on your WordPress servers.

* **Easy to setup and configure**
The setup wizard is a three step configuration that gets you started in less than 5 minutes.

**Check out other paid tools on ahrefs.com**

[youtube https://www.youtube.com/watch?v=_oU8lclN114]

<a href="https://ahrefs.com" target="_blank">Official Homepage</a> | <a href="https://ahrefs.com/big-data" target="_blank">Our Big Data</a> | <a href="https://ahrefs.com/blog/unique-features-ahrefs/" target="_blank">Things Only Ahrefs Can Do</a> | <a href="https://ahrefs.com/academy/" target="_blank">Ahrefs Academy</a>

== Installation ==

Getting started with Ahrefs SEO consists of just two simple steps: installing and setting up the plugin.

### From within WordPress

1. Visit 'Plugins > Add New'
2. Search for 'Ahrefs SEO'
3. Install Ahrefs SEO once it appears
4. Activate Ahrefs SEO from your Plugins page;
5. Go to "after activation" below.

### Manually

1. Upload the ‘ahrefs-seo’ folder to the /wp-content/plugins/ directory;
2. Activate the Ahrefs SEO plugin through the ‘Plugins’ menu in WordPress;
3. Go to ‘after activation’ below.

### After Activation

1. Click on the Ahrefs SEO tab;
2. You should see the Ahrefs SEO setup wizard;
3. Go through the setup wizard and follow the steps to connect Ahrefs to your site;
4. Voila, that's it - you're ready to use the plugin!

== Frequently Asked Questions ==

= Can I use the plugin if I am not a paying customer of Ahrefs? =

Yes you can. You will be able to perform a full content audit for your site only.

= I am a paying customer of Ahrefs. How does this affect my subscription? =

Your subscription of Ahrefs comes with Integration Rows that will be consumed with each API request. You can check the remaining balance in the plugin under 'Settings' or in <a href="https://ahrefs.com/api/profile" target="_blank">your Ahrefs account under 'API profile'.</a>

= How does the content audit in Ahrefs SEO plugin work? =

A content audit is where you analyze the performance of all content on your site to determine whether it should be kept as-is, updated, deleted, consolidated, or redirected.

This results in a healthier site with fewer underperforming low-quality pages.

It’s the online equivalent of a spring clean. You’re getting rid of anything and everything you don’t need and freshening up the place for your visitors—and Google.

Read more about how the plugin works <a href="https://help.ahrefs.com/en/articles/3901720-how-does-the-ahrefs-seo-wordpress-plugin-work" target="_blank">here.</a>

= Do I need to connect Ahrefs & Google accounts for this plugin to work? =

Ahrefs, Google Analytics & Google Search Console are required connections for the plugin to work. Giving actual site organic traffic & keyword position data to the plugin will allow the content audit to perform optimally, giving you better and more accurate recommendations.

= Does this replace other SEO plugins that I have installed? =

No this does not. The Ahrefs SEO plugin focuses on auditing your site content and is meant to work alongside some of your favorite WordPress SEO plugins.

It works perfectly well with other plugins like Yoast, Rank Math, SEOPress.

== Screenshots ==

1. Simple setup wizard with Ahrefs and Google connectors
2. Audit your website content to improve your Performance Score and optimize your organic traffic
3. Get suggested actions for each of the page on your site 
4. Set or change target keywords for your pages based on top queries from Search Console or our analysis

== Changelog ==

= 0.7.5 =
Release date: March 3rd, 2021

* Improved error handling from fatal errors vs temporary errors
* More informative error messages & tips

= 0.7.4 =
Release date: February 11th, 2021

* Improved GSC API request handlers with greater min delay & just-in-time requests
* Improved ajax ping logic & prevent cron & ping from running concurrently
* Perform compatibility check before connecting APIs & running audit
* Show notifications if compatibility checks failed
* Group same error message if errors occur

= 0.7.3 =
Release date: January 14th, 2021

* GA4 upgrade
* Changed API calls to use different filters, request size & constants for parameters
* Added delays (shared between threads) after successful API calls
* Other bug fixes

= 0.7.2 =
Release date: January 7th, 2021

* Migrate to Google client v2

= 0.7.1 =
Release date: December 9th, 2020

* Increased Google API request delay to prevent rate limit errors
* Reset GA & GSC accounts if updating from a version before 0.7
* Flush cache after Google accounts are disconnected
* Exclude items with unverified credentials during autodetect
* Scroll to error message when error is present
* Fixed sql for table creation, when MySQL version is unsupported
* Improved error logging to bugsnag

= 0.7.0 =
Release date: November 26th, 2020

* Made GA & GSC connections mandatory
* Changed suggested action naming & logic
* Changed folder structure and naming
* Changed score calculation method & chart UI
* Implemented approval feature for target keywords
* Implemented automatic exclusion rules like noindex page etc.
* Fixed google connection issue on multiple views
* Implemented autodetection for google profile selection
* Deprecated backlink explorer

= 0.6.7 =
Release date: July 30th, 2020

* do not allow user to select GSC item with low permissions level
* fix issue on the Google accounts page & php notice

= 0.6.6 =
Release date: July 8th, 2020

* Fixed unexpected result in GA websiteUrl property with null value
* Shows permissions level for GSC to user for further diagnostics

= 0.6.5 =
Release date: June 26th, 2020

* Fixed sites using Domain property instead of URL prefix in Google Search Console
* Shows disconnect reason in GSC settings screen
* Fixed number or required rows - count only active posts in GSC settings screen
* Heartbeat API ping interval for content audit is now max 2 minutes

= 0.6.4 =
Release date: June 26th, 2020

* Hot fix for bugsnag issue in load GSC accounts

= 0.6.3 =
Release date: June 24th, 2020

* Fixed sites using Google Analytics v2 API returning token as array
* Fixed PHP memory limit issue by not limiting GA API request to only 1000 pages at once
* Fixed 504 gateway timeout error by minimizing execution time
* Check for GSC connection errors for error reporting

= 0.6.2 =
Release date: June 17th, 2020

* Fixed some Google Search Console API errors

= 0.6.1 =
Release date: June 16th, 2020

* Fixed some Google Search Console API errors

= 0.6.0 =
Release date: June 11th, 2020

* You can now select target keywords for your posts & pages
* Option to enable Google Search Console connection, giving you queries data for your target keywords
* If Google Search Console is not enabled, the plugin runs TF-IDF to give some suggested keywords
* Check these keywords easily in Ahrefs
* Better content audit recommendations on redirection or merging based on target keywords
* Backlinks retrieval method has been rewritten to make it much more efficient for large sites
* Options to turn off notifications
