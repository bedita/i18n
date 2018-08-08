# I18n plugin for BEdita4 & CakePHP

## Installation

You can install this plugin BEdita4/CakePHP application using [composer](http://getcomposer.org).

The recommended way to install composer packages is:

```bash
composer require bedita/i18n
```

## Use gettext shell

Gettext shell provide util to update i18n locale files in BEdita4 apps and plugins.

```bash
$ bin/cake gettext --help
Usage:
cake gettext [subcommand] [-h] [-q] [-v]

Subcommands:

update  Update po and pot files

To see help on a subcommand use `cake gettext [subcommand] --help`

Options:

--help, -h     Display this help.
--quiet, -q    Enable quiet output.
--verbose, -v  Enable verbose output.
```

```bash
$ bin/cake gettext update --help
Create / update i18n files
`cake gettext update --frontend <frontend path>` will update po/pot file for the frontend
`cake gettext update --plugin <plugin path>` will update po/pot file for the plugin

Usage:
cake gettext update [-h] [-q] [-v] [-f] [-p]

Options:

--help, -h      Display this help.
--quiet, -q     Enable quiet output.
--verbose, -v   Enable verbose output.
--frontend, -f  The frontend path, for i18n update.
--plugin, -p    The plugin path, for i18n update.
```
