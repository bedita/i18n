# I18n plugin for BEdita4 & CakePHP

[![Build Status](https://travis-ci.com/bedita/i18n.svg)](https://travis-ci.com/bedita/i18n)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bedita/i18n/badges/quality-score.png)](https://scrutinizer-ci.com/g/bedita/i18n)
<!-- [![Code Coverage](https://codecov.io/gh/bedita/i18n/graph/badge.svg)](https://codecov.io/gh/bedita/i18n) -->

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

## Middleware and Helper

First of all you need to setup an `I18n` configuration in your application bootstrap

```php
/*
 * I18n configuration.
 */
Configure::write('I18n', [
    // list of locales supported
    // locale => primary language code for that locale
    'locales' => [
        'en_US' => 'en',
        'it_IT' => 'it',
    ],
    // default primary language code
    'default' => 'en',
    // list of languages supported
    // primary language code => humanized language
    'languages' => [
        'en' => 'English',
        'it' => 'Italiano',
    ],
]);
```

### I18nMiddleware

In order to use the `I18nMiddleware` you have to add it to `MiddlewareQueue` in app `src/Application.php`

```php
namespace App;

use BEdita\I18n\Middleware\I18nMiddleware;
use Cake\Http\BaseApplication;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;

/**
 * Application setup class.
 */
class Application extends BaseApplication
{
    /**
     * {@inheritDoc}
     */
    public function middleware($middlewareQueue) : MiddlewareQueue
    {
        $middlewareQueue
            ->add(ErrorHandlerMiddleware::class)
            ->add(AssetMiddleware::class)

            // Add I18n middleware.
            ->add(new I18nMiddleware())

            ->add(new RoutingMiddleware($this));

        return $middlewareQueue;
    }
}
```

In this way the middleware just takes care of setup the right locale using the URI path and a configuration.
For example navigating to http://example.com/it/page/one the middleware setup locale to `it_IT`
using the above configuration example.

You can configure the middleware to redirect some urls to their i18n versions

```php
$middlewareQueue->add(new I18nMiddleware([
    'match' => ['/'],
    'startWith' => ['/help/', '/about/'],
]));
```

All URI paths matching exactly something in `'match'` key or starting with one entry in `'startWith'`
will be redirect to the same URL but with the detected language as prefix, for example `/it/help`.

### I18nHelper

In order to use the helper you need to initialize it in your `AppView::initialize()` method

```php
public function initialize() : void
{
    parent::initialize();

    $this->loadHelper('BEdita/I18n.I18n');
}
```

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
