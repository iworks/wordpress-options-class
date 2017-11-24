WordPress Options Class
=======================

Options Class for WordPress plugins &amp; themes

Changelog
---------

##### 2.6.3 (2017-xx-xx)

* Handle "checkbox", and "switch_button" fields.
* Improved submenu building.
* Added ability to produce "hidden" name, prefixed by underscore.

##### 2.6.3 (2017-10-09)

* Added $option['options'] sanitization.

##### 2.6.2 (2017-05-23)

* Fixed a problem with classes for textarea tag.

##### 2.6.1 (2017-05-20)

* Fixed problem with jQuery UI slider, there was 100 as default for min value.

##### 2.6.0 (2017-05-12)

* IMPROVMENT: added slide for checkboxes.
* IMPROVMENT: added select2.
* IMPROVMENT: added indivudual functions to render elements.

##### 2.4.0 (2015-11-11)

* IMPROVMENT: added ability to create option page in any point of menu
* IMPROVMENT: added meta boxes
* IMPROVMENT: added page load hook
* IMPROVMENT: added script enqueue
* IMPROVMENT: added build-in option page
* IMPROVMENT: integrate tabs display (no more external js in theme or plugin)

##### 2.1.0 (2014-01-11)

* BUGFIX: fix remebering last used tab for theme usage

##### 2.0.0 (2014-01-06)

* IMPROVMENT: init can read both setting: plugin & themes
* IMPROVMENT: add option array filter
* IMPROVMENT: add method to register settings without prefix

##### 1.7.7 (2013-12-27)

* BUGFIX: default value only when is need

##### 1.7.6 (2013-12-18)

* IMPROVMENT: added remove two options to "decativate plugin" function
* IMPROVMENT: added email type input

##### 1.7.5 (2013-12-08)

* BUGFIX: repair some php warnigs

##### 1.7.4 (2013-08-27)

* IMPROVMENT: added filter to change options
* IMPROVMENT: added get_option_group function
* IMPROVMENT: added helper for wp_dropdown_categories
* IMPROVMENT: added helper for wp_dropdown_pages

##### 1.7.3 (2013-06-04)

* BUGFIX: repair get_option, to prevent return always default if null
* IMPROVMENT: added force_default to get_option method

##### 1.7.2 (2013-05-23)

* IMPROVMENT: add min/max attributes to filed type "number"
* IMPROVMENT: add get_option_name method

