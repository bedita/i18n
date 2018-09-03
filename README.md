# I18n plugin for BEdita4 & CakePHP

## Installation

You can install this BEdita4/CakePHP plugin using [composer](http://getcomposer.org) like this:

```bash
composer require bedita/i18n
```

## Setup

In order to use the plugin you have to load it in you application using

```php
Plugin::load('BEdita/I18n');
```

in either `config/bootstrap.php` or `config/bootstrap_cli.php`.

## Gettext shell

`gettext` shell command provides a method to update I18N locale files in BEdita4 apps and plugins.

A working `msgmerge` binary on your system is necessary. In most Linux systems this is provided via `gettext` package.

By simply typing

```bash
bin/cake gettext update
```

your code inside `/src` and `/config` is parsed and gettext strings are extracted looking for `__('Some string')` expressions.
Then:

* `src/Locale/master.pot` gettext master file is created or updated;
* all locale files like `src/Locale/{locale}/default.po` are also created or updated - where `{locale}` is the usual locale expression like `en-US`, `de-DE` or `fr-FR`.

Command options:

```bash
--help, -h      Display this help.
--quiet, -q     Enable quiet output.
--verbose, -v   Enable verbose output.
--app, -a       The app path, for i18n update.
--plugin, -p    The plugin path, for i18n update.
```

You may invoke this command on a different application or plugin path via `--app` or `--plugin` options.
