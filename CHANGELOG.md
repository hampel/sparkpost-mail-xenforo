CHANGELOG
=========

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
