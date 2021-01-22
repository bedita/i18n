<?php
declare(strict_types=1);

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
namespace BEdita\I18n\Test\TestCase;

use BEdita\I18n\Middleware\I18nMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\TestSuite\TestCase;
use TestApp\Application;

/**
 * {@see BEdita\I18n\Plugin} Test Case
 *
 * @coversDefaultClass \BEdita\I18n\Plugin
 */
class PluginTest extends TestCase
{
    /**
     * Test `console` method
     *
     * @return void
     * @covers ::middleware()
     */
    public function testMiddleware(): void
    {
        $app = new Application(CONFIG);
        $middlewareQueue = $app->middleware(new MiddlewareQueue());
        static::assertEquals(1, $middlewareQueue->count());
        static::assertInstanceOf(RoutingMiddleware::class, $middlewareQueue->current());

        $middlewareQueue = $app->pluginMiddleware($middlewareQueue);
        static::assertEquals(2, $middlewareQueue->count());
        static::assertInstanceOf(I18nMiddleware::class, $middlewareQueue->current());
        $middlewareQueue->next();
        static::assertInstanceOf(RoutingMiddleware::class, $middlewareQueue->current());
    }
}
