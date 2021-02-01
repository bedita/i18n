<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2020 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\I18n;

use BEdita\I18n\Middleware\I18nMiddleware;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;

/**
 * Plugin class for BEdita\WebTools.
 */
class Plugin extends BasePlugin
{
    /**
     * Do bootstrapping or not
     *
     * @var bool
     */
    protected $bootstrapEnabled = false;

    /**
     * Load routes or not
     *
     * @var bool
     */
    protected $routesEnabled = false;

    /**
     * Enable middleware
     *
     * @var bool
     */
    protected $middlewareEnabled = false;

    /**
     * Setup the I8nMiddleware.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue = parent::middleware($middlewareQueue);

        /**
         *  Add I18n middleware.
         *
         *  Define when I18n rules are applied with `/:lang` prefix:
         *    - 'match': array of URL paths, if there's an exact match rule is applied
         *    - 'startWith': array of URL paths, if current URL path starts with one of these rule is applied
         *    - 'switchLangUrl': reserved URL (for example `/lang`) used to switch language and redirect to referer URL.
         *               Disabled by default.
         *    - 'cookie': array for cookie that keeps the locale value. By default no cookie is used.
         *       - 'name': cookie name
         *       - 'create': set to `true` if the middleware is responsible of cookie creation
         *       - 'expire': used when `create` is `true` to define when the cookie must expire
         */
        $middlewareQueue->insertBefore(
            RoutingMiddleware::class,
            new I18nMiddleware((array)Configure::read('I18n', []))
        );

        return $middlewareQueue;
    }
}
