# I18n plugin for BEdita4 & CakePHP

[![Build Status](https://travis-ci.com/bedita/i18n.svg?branch=master)](https://travis-ci.com/bedita/i18n)
[![codecov](https://codecov.io/gh/bedita/i18n/branch/master/graph/badge.svg)](https://codecov.io/gh/bedita/i18n)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/bedita/i18n/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/bedita/i18n?branch=master)
<!-- [![Code Coverage](https://codecov.io/gh/bedita/i18n/graph/badge.svg)](https://codecov.io/gh/bedita/i18n) -->

## Installation

You can install this BEdita4/CakePHP plugin using [composer](http://getcomposer.org) like this:

```bash
composer require bedita/i18n
```

## Setup

In order to use the plugin you have to load it in your `Application::bootstrap()` using

```php
$this->addPlugin('BEdita/I18n');
```

## Middleware and Helper

First of all you need to setup an `I18n` configuration in your application `config/bootstrap.php` (or if you prefer in `config/app_local.php`)

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

    /** Middleware specific conf **/
    // array of URL paths, if there's an exact match rule is applied
    'match' => ['/'],
    // array of URL paths, if current URL path starts with one of these rule is applied
    'startWith' => ['/help', '/about'],
    //reserved URL (for example `/lang`) used to switch language and redirect to referer URL.
    'switchLangUrl' => '/lang',
    // array for cookie that keeps the locale value. By default no cookie is used.
    'cookie' => [
         'name' =>  'i18n-lang', //cookie name
         'create' => true, // set to `true` if the middleware is responsible of cookie creation
         'expire' => '+1 year', // used when `create` is `true` to define when the cookie must expire
    ],
]);
```

### I18nMiddleware

Adding `BEdita/I18n` plugin in app `Application::bootstrap()` method, the `I18nMiddleware`
will be added in middleware queue just before `RoutingMiddleware`.

`I18n` configuration will be used to setup middleware configuration.

```php
namespace App;

use Cake\Http\BaseApplication;

/**
 * Application setup class.
 */
class Application extends BaseApplication
{
    /**
     * {inheritDoc}
     */
    public function bootstrap(): void
    {
        parent::bootstrap();

        $this->addPlugin('BEdita/I18n');
    }

    // other stuff here
}
```

You can also decide to not add `I18nMiddleware` since your app doesn't need it or to
programmatically add it in your application:

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
     * {inheritDoc}
     */
    public function bootstrap(): void
    {
        parent::bootstrap();

        // Do not add I18nMiddleware automatically
        $this->addPlugin('BEdita/I18n', ['middleware' => false]);
    }

    /**
     * {@inheritDoc}
     */
    public function middleware($middlewareQueue) : MiddlewareQueue
    {
        $middlewareQueue
            ->add(ErrorHandlerMiddleware::class)
            ->add(AssetMiddleware::class)

            // Add programmatically I18n middleware.
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

You can also configure the middleware to use a cookie to store the locale

```php
$middlewareQueue->add(new I18nMiddleware([
    'cookie' =>[
        'name' => 'I18nLocale',
        'create' => true, // the middleware will create the cookie (default false)
        'expire' => '+1 month', // cookie expiring time (default +1 year)
    ],
]));
```

### I18nRoute

`I18nRoute` class can be used to simplify the way you write and match routing rules.
For example

```php
$routes->connect(
    '/pages',
    [
        'controller' => 'Pages',
        'action' => 'index',
    ],
    [
        '_name' => 'pages:index',
        'routeClass' => 'BEdita/I18n.I18nRoute',
    ]
);
```

maps to `/:lang/pages` and the language code defined in `I18n.languages` configuration will be used as route patterns on `:lang` param.

So the above rule is the same of

```php
$routes->connect(
    '/:lang/pages',
    [
        'controller' => 'Pages',
        'action' => 'index',
    ],
    ['_name' => 'pages:index']
)
->setPatterns(['lang' => 'it|en']);
```

If the current language is `it` you can obtain the localized url as

```php
// default
$url = \Cake\Routing\Router::url(['_name' => 'pages:index']);
echo $url; // prints /it/pages

// get url with another supported lang
$url = \Cake\Routing\Router::url([
    '_name' => 'pages:index',
    'lang' => 'en',
]);
echo $url; // prints /en/pages
```

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
