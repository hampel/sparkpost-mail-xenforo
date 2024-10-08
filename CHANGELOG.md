CHANGELOG
=========

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
