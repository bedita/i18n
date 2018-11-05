<?php
/**
 * BEdita, API-first content management framework
 * Copyright 2018 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\I18n\Middleware;

use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Http\ServerRequest;
use Cake\I18n\I18n;
use Cake\Utility\Hash;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\RedirectResponse;

/**
 * i18n middleware.
 *
 * It is responsible to setup the right locale based on URI path as `/:lang/page/to/reach`.
 * It is configurable to redirect URI matches some rules using `:lang` prefix.
 */
class I18nMiddleware
{
    use InstanceConfigTrait;

    /**
     * Define when I18n rules are applied with `/:lang` prefix:
     *  - 'match': array of URL paths, if there's an exact match rule is applied
     *  - 'startWith': array of URL paths, if current URL path starts with one of these rule is applied
     *
     * @var array
     */
    protected $_defaultConfig = [
        'match' => [],
        'startWith' => [],
        'cookieName' => 'i18nSessionLanguage',
    ];

    /**
     * Middleware constructor.
     *
     * @param array $config Configuration.
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
    }

    /**
     * Setup used language code and locale from URL prefix `/:lang`
     *
     * Add `/:lang` (language code) prefix to a URL if a match is found
     * using `match` and `startWith` configurations.
     *
     * At the moment only primary languages are correctly handled as language codes to be used as URL prefix.
     *
     * @param \Cake\Http\ServerRequest $request The request.
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param callable $next Callback to invoke the next middleware.
     *
     * @return \Psr\Http\Message\ResponseInterface A response
     */
    public function __invoke(ServerRequest $request, ResponseInterface $response, $next) : ResponseInterface
    {
        $path = $request->getUri()->getPath();

        $redir = false;
        foreach ($this->getConfig('startWith') as $needle) {
            if (stripos($path, $needle) === 0) {
                $redir = true;
            }
        }

        $httpLocale = \Locale::acceptFromHttp($request->getHeaderLine('Accept-Language'));

        // get locale from cookie, if available
        $cookieName = $this->getConfig('cookieName');
        $httpLocale = $request->getCookie($cookieName, $httpLocale);

        if (!$redir && !in_array($path, $this->getConfig('match'))) {
            $this->setupLocale($path, $httpLocale);

            return $next($request, $response);
        }

        $lang = Configure::read('I18n.default');
        if ($httpLocale) {
            $localeLang = Configure::read(sprintf('I18n.locales.%s', $httpLocale));
            if ($localeLang) {
                $lang = $localeLang;
            } else {
                // try with primary language
                $primary = \Locale::getPrimaryLanguage($httpLocale);
                if (Configure::read(sprintf('I18n.languages.%s', $primary))) {
                    $lang = $primary;
                }
            }
        }
        $statusCode = 301;

        $uri = $request->getUri()->withPath(sprintf('%s%s', $lang, $path));

        return new RedirectResponse($uri, $statusCode);
    }

    /**
     * Setup current locale and language code from request URL.
     * Request URL must have a `/:lang` prefix as primary language in order to work.
     *
     * @param string $path The URL path.
     * @param string $locale Detected HTTP locale.
     * @return void
     */
    protected function setupLocale(string $path, string $locale) : void
    {
        $defaultLang = Configure::read('I18n.default');

        // setup detected primary lang in 'I18n.lang'
        $primaryLang = Hash::get(explode('/', $path), '1');
        if (empty($primaryLang) || !Configure::read(sprintf('I18n.languages.%s', $primaryLang))) {
            $primaryLang = $defaultLang;
        }
        Configure::write('I18n.lang', $primaryLang);

        // if detected locale matches language code let's use it, if not use a primary lang locale
        if ($primaryLang !== Configure::read(sprintf('I18n.locales.%s', $locale))) {
            $locales = array_flip((array)Configure::read('I18n.locales'));
            $locale = Hash::get($locales, $primaryLang);
        }
        I18n::setLocale($locale);
    }
}
