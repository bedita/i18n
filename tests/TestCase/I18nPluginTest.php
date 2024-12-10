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

use ArrayIterator;
use BEdita\I18n\Middleware\I18nMiddleware;
use BEdita\I18n\Test\App\Application;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\TestSuite\TestCase;
use Iterator;

/**
 * Test class for I18nPlugin
 */
class I18nPluginTest extends TestCase
{
    /**
     * Test `middleware` method
     *
     * @return void
     */
    public function testMiddleware(): void
    {
        $app = new Application(CONFIG);
        $queue = $app->middleware(new MiddlewareQueue());
        $queue = $app->pluginMiddleware($queue);

        if (!($queue instanceof Iterator)) {
            $queue = new ArrayIterator(array_map([$queue, 'get'], range(0, count($queue) - 1)));
        }
        static::assertInstanceOfTuple([I18nMiddleware::class, RoutingMiddleware::class], $queue);
    }

    /**
     * Assert that each iterable's element is instance of the respective class in the tuple.
     *
     * @param string[] $expected Expected class names.
     * @param iterable $actual Actual object.
     * @return void
     */
    protected static function assertInstanceOfTuple(array $expected, iterable $actual): void
    {
        static::assertSameSize($expected, $actual);

        reset($expected);
        foreach ($actual as $it) {
            static::assertInstanceOf(current($expected), $it);
            next($expected);
        }
    }
}
