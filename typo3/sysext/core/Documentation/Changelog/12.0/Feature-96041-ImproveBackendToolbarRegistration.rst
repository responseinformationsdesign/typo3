.. include:: ../../Includes.txt

======================================================
Feature: #96041 - Improve Backend toolbar registration
======================================================

See :issue:`96041`

Description
===========

The toolbar in the TYPO3 backend is well known by users as it provides
e.g. the personal bookmarks or a common used feature for administrators:
the "flush caches" action.

It's furthermore also possible for extension authors to add their own
toolbar items. The registration therefore had to be done in the
:php:`LocalConfiguration.php` file.

Since the introduction of the symfony service container in TYPO3 v10,
it's possible to autoconfigure services. This feature is now also used
for the toolbar items. Therefore, the previous registration step is
now superfluous. All toolbar items are now automatically tagged and
registered based on the implemented :php:`ToolbarItemInterface`.

Impact
======

Custom toolbar items are now automatically registered, based on
the implemented interface, through the service configuration.

.. index:: Backend, PHP-API, ext:backend
