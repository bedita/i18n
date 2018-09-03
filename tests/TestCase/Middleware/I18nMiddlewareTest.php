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
namespace BEdita\I18n\Test\Middleware;

use BEdita\I18n\Middleware\I18nMiddleware;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\TestSuite\TestCase;
use Cake\I18n\I18n;

/**
 * {@see \BEdita\I18n\Middleware\I18nMiddleware} Test Case
 *
 * @coversDefaultClass \BEdita\I18n\Middleware\I18nMiddleware
 */
class I18nMiddlewareTest extends TestCase
{

    /**
     * {@inheritDoc}
     */
    public function setUp() : void
    {
        parent::setUp();

        Configure::write('I18n', [
            'locales' => [
                'en_US' => 'en',
                'it_IT' => 'it',
            ],
            'default' => 'en',
            'languages' => [
                'en' => 'English',
                'it' => 'Italiano',
            ],
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown() : void
    {
        parent::tearDown();

        Configure::delete('I18n');
    }

    /**
     * Data provider for `testStatus()`
     *
     * @return array
     */
    public function statusProvider() : array
    {
        return [
            'noConfig' => [
                200, // expected
                [], // middleware conf
                [ // server request
                    'REQUEST_URI' => '/page',
                ],
            ],
            'startsWithNoMatch' => [
                200,
                [
                    'startWith' => ['/help'],
                ],
                [
                    'REQUEST_URI' => '/page',
                ],
            ],
            'startsWithMatch' => [
                301,
                [
                    'startWith' => ['/help'],
                ],
                [
                    'REQUEST_URI' => '/helper',
                ],
            ],
            'matchNoMatch' => [
                200,
                [
                    'match' => ['/help'],
                ],
                [
                    'REQUEST_URI' => '/help/pages',
                ],
            ],
            'matchMatch' => [
                301,
                [
                    'match' => ['/help'],
                ],
                [
                    'REQUEST_URI' => '/help',
                ],
            ],
        ];
    }

    /**
     * Test response status invoking middleware.
     *
     * @param int $expected The HTTP status code expected
     * @param array $conf The configuration passed to middleware
     * @param array $server The server vars
     * @return void
     *
     * @dataProvider statusProvider
     * @covers ::__construct()
     * @covers ::__invoke()
     */
    public function testStatus($expected, array $conf, array $server) : void
    {
        $request = ServerRequestFactory::fromGlobals($server);

        $response = new Response();

        $next = function ($req, $res) {
            return $res;
        };

        $middleware = new I18nMiddleware($conf);
        $response = $middleware($request, $response, $next);

        static::assertEquals($expected, $response->getStatusCode());
    }

    /**
     * Data Provider for `testRedirectPath`
     *
     * @return array
     */
    public function redirectPathProvider() : array
    {
        return [
            'missingAcceptLanguage' => [
                'http://example.com/en/help',
                [
                    'match' => ['/help'],
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/help',
                ],
            ],
            'configuredLocaleFound' => [
                'http://example.com/it/help',
                [
                    'match' => ['/help'],
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/help',
                    'HTTP_ACCEPT_LANGUAGE' => 'it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7,la;q=0.6',
                ],
            ],
            'configuredLocaleAndPrimaryNotFound' => [
                'http://example.com/en/help',
                [
                    'match' => ['/help'],
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/help',
                    'HTTP_ACCEPT_LANGUAGE' => 'es-ES,es;q=0.9,en-US;q=0.8,en;q=0.7,la;q=0.6',
                ],
            ],
            'configuredLocaleNotFoundButPrimaryFound' => [
                'http://example.com/en/help',
                [
                    'match' => ['/help'],
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/help',
                    'HTTP_ACCEPT_LANGUAGE' => 'en-GB,en;q=0.9,it-IT;q=0.8,it;q=0.7,la;q=0.6',
                ],
            ],
        ];
    }

    /**
     * Test path set in redirect response
     *
     * @param string $expected The response path expected
     * @param array $conf The configuration passed to middleware
     * @param array $server The server vars
     * @return void
     *
     * @dataProvider redirectPathProvider
     * @covers ::__invoke()
     */
    public function testRedirectPath($expected, array $conf, array $server) : void
    {
        $request = ServerRequestFactory::fromGlobals($server);

        $response = new Response();

        $next = function ($req, $res) {
            return $res;
        };

        $middleware = new I18nMiddleware($conf);
        $response = $middleware($request, $response, $next);

        static::assertEquals(301, $response->getStatusCode());
        static::assertEquals($expected, $response->getHeaderLine('Location'));
    }


    /**
     * Data provider for `testSetupLocale()`
     *
     * @return array
     */
    public function setupLocaleProvider() : array
    {
        return [
            'useDefault' => [
                [
                    'lang' => 'en',
                    'locale' => 'en_US',
                ],
                [
                    'REQUEST_URI' => '/help',
                ]
            ],
            'notValidLang' => [
                [
                    'lang' => 'en',
                    'locale' => 'en_US',
                ],
                [
                    'REQUEST_URI' => '/es/help',
                ]
            ],
            'setLocaleByPath' => [
                [
                    'lang' => 'it',
                    'locale' => 'it_IT',
                ],
                [
                    'REQUEST_URI' => '/it/help',
                ]
            ],
        ];
    }

    /**
     * Test setup Locale method
     *
     * @param array $expected The expected values (locale and lang)
     * @param array $server The server vars
     * @return void
     *
     * @dataProvider setupLocaleProvider
     * @covers ::setupLocale()
     */
    public function testSetupLocale(array $expected, array $server) : void
    {
        $request = ServerRequestFactory::fromGlobals($server);

        $response = new Response();

        $next = function ($req, $res) {
            return $res;
        };

        $middleware = new I18nMiddleware();
        $response = $middleware($request, $response, $next);

        static::assertEquals($expected['locale'], I18n::getLocale());
        static::assertEquals($expected['lang'], Configure::read('I18n.lang'));
    }
}
