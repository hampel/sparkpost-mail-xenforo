CHANGELOG
=========

5.0.0 (2026-09-07)
------------------

* **PHP 8.3.0 or later is now required** - PHP 8.1 and 8.2 are no longer supported
* replace the bundled `hampel/symfonymailer-sparkpost` transport and this add-on's hand-written 
SparkPost API client with the `hampel/sparkpost` and `hampel/sparkpost-transport` packages, which are 
maintained and tested independently of the add-on
* SparkPost API calls now go through XenForo's own outbound HTTP stack, so the board's proxy 
configuration and its checks on untrusted requests apply to them
* auto-replies and opt-in confirmations are no longer treated as delivery failures - an out-of-office 
reply previously recorded a soft bounce against a recipient whose address works perfectly well
* message event fetching now pages through SparkPost's own cursor, and honours the `Retry-After` 
period SparkPost sends when rate limiting instead of always waiting two minutes
* test mode now rewrites the SMTP envelope rather than the `To:` header, so a test message is 
delivered to the sink while still reading as addressed to the real recipient - previously the 
`.sink.sparkpostmail.com` suffix was visible to whoever received one
* the outbound email test page now reports how many recipients SparkPost rejected when it accepts a 
transmission but refuses its recipients
* remove the 47 unused `sparkpostmail_bounce_classification_*` phrases - nothing displayed them
* drop the `nesbot/carbon` dependency and its chain
* latest composer dependencies

4.0.1 (2026-09-03)
------------------

* bugfix: message event processing failed when the Monolog addon was not installed
* bugfix: SparkPost error details render correctly again on the outbound email test page - the 
message, description and error code fields were empty and the code shown was the HTTP status 
rather than SparkPost's own
* remove a template modification which no longer matched any XenForo 2.3 template
* align the bundled Symfony packages with the versions XenForo itself ships, so that the versions 
Composer resolves are the versions which actually run
* latest composer dependencies, including hampel/symfonymailer-sparkpost 1.1.4

4.0.0 (2025-09-03)
------------------

* drop the usage of sparkpost/sparkpost package because it hasn't been updated in many years - write our own SparkPost 
API wrapper instead
* convert to using XF 2.3 classnames instead of short names
* add CLI commands to fetch message events, process message events and prune message events
* add monolog logging support for most functions
* convert email bounce processor into the MessageEventService
* move to latest version of hampel/symfonymailer-sparkpost to fix bug with ReplyTo headers
* latest composer dependencies

3.1.1 (2024-12-30)
------------------

* change execution order of XF\Admin\Controller\Tools class extension 

3.1.0 (2024-12-30)
------------------

* handle WhatsNewDigest emails in this addon rather than via adapter
* check for WndSparkPost adapter during setup and fail until it is removed - it is incompatible with this version

3.0.4 (2024-10-16)
------------------

* latest composer dependencies
* assert admin permission on tools

3.0.3 (2024-10-04)
------------------

* install latest composer dependencies to fix a bug in exception handling for AbstractHttpTransport

3.0.2 (2024-09-09)
------------------

* bugfix: from header wasn't being set correctly in underlying SparkPost library - new version released

3.0.1 (2024-08-14)
------------------

* bugfix: need to install php-http/message and php-http/message-factory for the SparkPost API to work

3.0.0 (2024-08-09)
------------------

* implement Symfony Mailer to work with XenForo 2.3

2.1.4 (2022-09-27)
------------------

* simplify message event API sanity checking - we need to ensure from time is at least 60 seconds earlier than to time

2.1.3 (2022-02-15)
------------------

* add composer dependency of `"symfony/translation": "^5.0"` (used by `nesbot/carbon`) to avoid 
  installing v6.0 which breaks compatibility with older versions installed by other addons 

2.1.2 (2022-02-15)
------------------

* some additional sanity checking on API call parameters to try and avoid errors returning from SparkPost

2.1.1 (2021-10-03)
------------------

* bugfix: strip uri prefix returned in uri from paged responses

2.1.0 (2021-06-11)
------------------

* required parameter specified after optional parameter in SubContainer/SparkPost::logJobProgress - reorder function 
  parameters to make this more usable
* required parameter specified after optional parameter in Test/AbstractTest::message - just make them both required
* explicitly list "egulias/email-validator": "^2.0" as a requirement so that we don't run into problems with the version
  shipped with XenForo

2.0.0 (2020-09-04)
------------------

* complete rewrite for XF 2.2 and Swiftmailer v6

1.0.2 (2020-08-29)
------------------

* split out transport construction so we can reuse it
* unit tests weren't working when there was no apikey configured
* stop this version from being installed on XF 2.2

1.0.1 (2020-05-16)
------------------

* removed unused variable which was causing E_NOTICEs

1.0.0 (2019-12-18)
------------------

* first working version
