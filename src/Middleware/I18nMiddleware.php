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
     *  - 'cookie': array for cookie that keeps the locale value. By default no cookie is used.
     *      - 'name': cookie name
     *      - 'create': set to `true` if the middleware is responsible of cookie creation
     *      - 'expire': used when `create` is `true` to define when the cookie must expire
     *
     * @var array
     */
    protected $_defaultConfig = [
        'match' => [],
        'startWith' => [],
        'cookie' => [
            'name' => null,
            'create' => false,
            'expire' => '+1 year',
        ],
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

        $locale = $this->detectLocale($request);

        if (!$redir && !in_array($path, $this->getConfig('match'))) {
            $this->setupLocale($locale);
            $response = $this->getResponseWithCookie($response, I18n::getLocale());

            return $next($request, $response);
        }

        $lang = Configure::read('I18n.default');
        if ($locale) {
            $localeLang = Configure::read(sprintf('I18n.locales.%s', $locale));
            if ($localeLang) {
                $lang = $localeLang;
            } else {
                // try with primary language
                $primary = \Locale::getPrimaryLanguage($locale);
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
     * Detect locale following the rules:
     *
     * 1. first try to detect from url path
     * 2. then try to detect from cookie
     * 3. finally try to detect it from HTTP Accept-Language header
     *
     * @param ServerRequest $request The request.
     * @return string
     */
    protected function detectLocale(ServerRequest $request) : string
    {
        $path = $request->getUri()->getPath();
        $urlLang = (string)Hash::get(explode('/', $path), '1');
        $locale = array_search($urlLang, (array)Configure::read('I18n.locales'));
        if ($locale !== false) {
            return $locale;
        }

        $locale = (string)$request->getCookie($this->config('cookie.name'));
        if (!empty($locale)) {
            return $locale;
        }

        return \Locale::acceptFromHttp($request->getHeaderLine('Accept-Language'));
    }

    /**
     * Setup locale and language code from passed `$locale`.
     * If `$locale` is not found in configuraion then use the default.
     *
     * @param string $locale Detected HTTP locale.
     * @return void
     */
    protected function setupLocale(?string $locale) : void
    {
        $i18nConf = Configure::read('I18n', []);
        $lang = Hash::get($i18nConf, sprintf('locales.%s', (string)$locale));
        if ($lang === null) {
            $lang = Hash::get($i18nConf, 'default');
            $locale = array_search($lang, (array)Hash::get($i18nConf, 'locales', []));
        }

        Configure::write('I18n.lang', $lang);
        I18n::setLocale($locale);
    }

    /**
     * Return a response object with the locale cookie set or updated.
     *
     * The cookie is added only if the middleware is configured to create cookie.
     *
     * @param ResponseInterface $response The response.
     * @param string $locale The locale string to set in cookie.
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function getResponseWithCookie(ResponseInterface $response, string $locale) : ResponseInterface
    {
        $name = $this->getConfig('cookie.name');
        $create = $this->getConfig('cookie.create', false);
        if ($create !== true || empty($name)) {
            return $response;
        }

        return $response->withCookie($name, [
            'value' => $locale,
            'expire' => strtotime($this->getConfig('cookie.expire', '+1 year')),
        ]);
    }
}
