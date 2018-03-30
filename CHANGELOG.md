Changelog - Yii2 Captcha Extended
=================================

Version 2.0.0 - March 28, 2018
------------------------------
- full rework for compatability with Yii 2.x
- added new options - custom character sets, resultMultiplier, randomUpperLowerCase
- composer installation

Version 1.0.2 - Sept 19, 2013
-----------------------------
- bugfix: skip captcha counter incrementation if clientajax validation is turned on. Otherwise captcha code would be silently invalidated after entering as many form fields as the number of allowed captcha attempts.

Version 1.0.1 - July 01, 2012
-----------------------------
- fixed client-side validation that generated incorrect validation hash

Version 1.0.0 - August 29, 2011
------------------------------
Initial release
