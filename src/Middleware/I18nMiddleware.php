<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2022 Atlas Srl, ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\I18n\Middleware;

use BEdita\I18n\Core\I18nTrait;
use Cake\Core\Configure;
use Cake\Core\InstanceConfigTrait;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\I18n\FrozenTime;
use Cake\I18n\I18n;
use Cake\Utility\Hash;
use Cake\Validation\Validation;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * i18n middleware.
 *
 * It is responsible to setup the right locale based on URI path as `/:lang/page/to/reach`.
 * It is configurable to redirect URI matches some rules using `:lang` prefix.
 */
class I18nMiddleware implements MiddlewareInterface
{
    use I18nTrait;
    use InstanceConfigTrait;

    /**
     * Define when I18n rules are applied with `/:lang` prefix:
     *  - 'match': array of URL paths, if there's an exact match rule is applied
     *  - 'startWith': array of URL paths, if current URL path starts with one of these rule is applied
     *  - 'switchLangUrl': reserved URL (for example `/lang`) used to switch language and redirect to referer URL.
     *                     Disabled by default.
     *  - 'cookie': array for cookie that keeps the locale value. By default no cookie is used.
     *      - 'name': cookie name
     *      - 'create': set to `true` if the middleware is responsible of cookie creation
     *      - 'expire': used when `create` is `true` to define when the cookie must expire
     *  - 'sessionKey': the session key where store locale. The session is used as fallback to detect locale if cookie is disabled.
     *                  Set `null` if you don't want session.
     *
     * @var array
     */
    protected $_defaultConfig = [
        'match' => [],
        'startWith' => [],
        'switchLangUrl' => null,
        'cookie' => [
            'name' => null,
            'create' => false,
            'expire' => '+1 year',
        ],
        'sessionKey' => null,
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
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The request handler.
     * @return \Psr\Http\Message\ResponseInterface A response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path !== '/') {
            $path = rtrim($path, '/'); // remove trailing slashes
        }

        if ($path === (string)$this->getConfig('switchLangUrl')) {
            return $this->changeLangAndRedirect($request);
        }

        $redir = false;
        foreach ($this->getConfig('startWith') as $needle) {
            if (stripos($path, $needle) === 0) {
                $redir = true;
            }
        }

        $locale = $this->detectLocale($request);

        if (!$redir && !in_array($path, $this->getConfig('match'))) {
            $this->setupLocale($locale);
            $this->updateSession($request, $this->getLocale());
            $response = $handler->handle($request);

            return $this->getResponseWithCookie($response, $this->getLocale());
        }

        $lang = $this->getDefaultLang();
        if ($locale) {
            $localeLang = Hash::get($this->getLocales(), $locale);
            if ($localeLang) {
                $lang = $localeLang;
            } else {
                // try with primary language
                $primary = \Locale::getPrimaryLanguage($locale);
                if (Hash::get($this->getLanguages(), $primary)) {
                    $lang = $primary;
                }
            }
        }

        $uri = $request->getUri()->withPath(sprintf('%s%s', $lang, rtrim($path, '/')));

        return new RedirectResponse($uri);
    }

    /**
     * Detect locale following the rules:
     *
     * 1. first try to detect from url path
     * 2. then try to detect from cookie
     * 3. finally try to detect it from HTTP Accept-Language header
     *
     * @param \Cake\Http\ServerRequest $request The request.
     * @return string
     */
    protected function detectLocale(ServerRequest $request): string
    {
        $path = $request->getUri()->getPath();
        $urlLang = (string)Hash::get(explode('/', $path), '1');
        $locale = array_search($urlLang, $this->getLocales());
        if ($locale !== false) {
            return (string)$locale;
        }

        $locale = (string)$request->getCookie($this->getConfig('cookie.name', ''));
        if (!empty($locale)) {
            return $locale;
        }

        $locale = $this->readSession($request);
        if (!empty($locale)) {
            return $locale;
        }

        return (string)\Locale::acceptFromHttp($request->getHeaderLine('Accept-Language'));
    }

    /**
     * Setup locale and language code from passed `$locale`.
     * If `$locale` is not found in configuraion then use the default.
     *
     * @param string $locale Detected HTTP locale.
     * @return void
     */
    protected function setupLocale(?string $locale): void
    {
        $locales = $this->getLocales();
        $lang = (string)Hash::get($locales, (string)$locale);
        if (empty($lang)) {
            $lang = $this->getDefaultLang();
            $locale = array_search($lang, $locales);
        }

        if (empty($lang) || $locale === false) {
            throw new InternalErrorException(
                __('Something was wrong with I18n configuration. Check "I18n.locales" and "I18n.default"')
            );
        }

        Configure::write('I18n.lang', $lang);
        I18n::setLocale($locale);
    }

    /**
     * Return a response object with the locale cookie set or updated.
     *
     * The cookie is added only if the middleware is configured to create cookie.
     *
     * @param \Psr\Http\Message\ResponseInterface $response The response.
     * @param string $locale The locale string to set in cookie.
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function getResponseWithCookie(ResponseInterface $response, string $locale): ResponseInterface
    {
        $name = $this->getConfig('cookie.name');
        $create = $this->getConfig('cookie.create', false);
        if ($create !== true || empty($name) || !$response instanceof Response) {
            return $response;
        }

        $expire = FrozenTime::createFromTimestamp(strtotime($this->getConfig('cookie.expire', '+1 year')));

        return $response->withCookie(new Cookie($name, $locale, $expire));
    }

    /**
     * Change lang and redirect to referer.
     *
     * Require query string `new` and `redirect`
     *
     * @param \Cake\Http\ServerRequest $request The request
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Cake\Http\Exception\BadRequestException When missing required query string or unsupported language
     */
    protected function changeLangAndRedirect(ServerRequest $request): ResponseInterface
    {
        if (!$this->getConfig('cookie.name') && !$this->getSessionKey()) {
            throw new \LogicException(
                __('I18nMiddleware misconfigured. `switchLangUrl` requires `cookie.name` or `sessionKey`')
            );
        }

        $new = (string)$request->getQuery('new');
        if (empty($new)) {
            throw new BadRequestException(__('Missing required "new" query string'));
        }

        $locale = array_search($new, $this->getLocales());
        if ($locale === false) {
            throw new BadRequestException(__('Lang "{0}" not supported', [$new]));
        }

        $redirect = (string)$request->getQuery('redirect', $request->referer(false));
        if (strpos($redirect, '/') !== 0 && !Validation::url($redirect, true)) {
            throw new BadRequestException(__('"redirect" query string not valid'));
        }

        $this->updateSession($request, $locale);

        $response = (new Response())
            ->withLocation($redirect)
            ->withDisabledCache()
            ->withStatus(302);

        return $this->getResponseWithCookie($response, $locale);
    }

    /**
     * Read locale from session.
     *
     * @param \Cake\Http\ServerRequest $request The request
     * @return string|null
     */
    protected function readSession(ServerRequest $request): ?string
    {
        $sessionKey = $this->getSessionKey();
        if ($sessionKey === null) {
            return null;
        }

        return $request->getSession()->read($sessionKey);
    }

    /**
     * Update session with locale.
     *
     * @param \Cake\Http\ServerRequest $request The request.
     * @param string $locale The locale string
     * @return void
     */
    protected function updateSession(ServerRequest $request, string $locale): void
    {
        $sessionKey = $this->getSessionKey();
        if ($sessionKey === null) {
            return;
        }

        $request->getSession()->write($sessionKey, $locale);
    }

    /**
     * Get the session key used to store locale.
     *
     * @return string|null
     */
    protected function getSessionKey(): ?string
    {
        $sessionKey = $this->getConfig('sessionKey');
        if (empty($sessionKey) || !is_string($sessionKey)) {
            return null;
        }

        return $sessionKey;
    }
}
