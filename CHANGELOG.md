CHANGELOG
=========

1.1.0 (2020-09-05)
------------------

* rename apikey parameter in preparation for XF 2.2 upgrade and addon v2.x
* put in checks to ensure code isn't called if SparkPost isn't configured
* disable sparkpost if we're still running this version of the addon after upgrading to XF 2.2 to prevent breaking
 the forum

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
