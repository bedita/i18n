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
namespace BEdita\I18n\Test\Middleware;

use BEdita\I18n\Middleware\I18nMiddleware;
use Cake\Core\Configure;
use Cake\Http\Cookie\Cookie;
use Cake\Http\Exception\BadRequestException;
use Cake\Http\Exception\InternalErrorException;
use Cake\Http\Response;
use Cake\Http\ServerRequestFactory;
use Cake\I18n\I18n;
use Cake\TestSuite\TestCase;
use Cake\Utility\Hash;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * {@see \BEdita\I18n\Middleware\I18nMiddleware} Test Case
 *
 * @coversDefaultClass \BEdita\I18n\Middleware\I18nMiddleware
 */
class I18nMiddlewareTest extends TestCase
{
    /**
     * Fake request handler
     *
     * @var \Psr\Http\Server\RequestHandlerInterface
     */
    protected $requestHandler;

    /**
     * @inheritDoc
     */
    public function setUp(): void
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

        $this->requestHandler = new class () implements RequestHandlerInterface {
            /**
             * Implementation of handle method
             *
             * @param ServerRequestInterface $request The request.
             * @return ResponseInterface
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response();
            }
        };
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        parent::tearDown();

        // reset locale to default value
        I18n::setLocale(I18n::getDefaultLocale());
        Configure::delete('I18n');
        $this->requestHandler = null;
        unset($_SESSION);
    }

    /**
     * Data provider for `testStatus()`
     *
     * @return array
     */
    public function statusProvider(): array
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
                302,
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
                302,
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
     * @dataProvider statusProvider
     * @covers ::__construct()
     * @covers ::process()
     */
    public function testStatus($expected, array $conf, array $server): void
    {
        $request = ServerRequestFactory::fromGlobals($server);
        $middleware = new I18nMiddleware($conf);
        $response = $middleware->process($request, $this->requestHandler);

        static::assertEquals($expected, $response->getStatusCode());
    }

    /**
     * Data Provider for `testRedirectPath`
     *
     * @return array
     */
    public function redirectPathProvider(): array
    {
        return [
            'rootPath' => [
                'http://example.com/en',
                [
                    'match' => ['/'],
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '',
                ],
            ],
            'rootPathEndsWithSlash' => [
                'http://example.com/en',
                [
                    'match' => ['/'],
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/',
                ],
            ],
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
            'pathWithOneTrailingSlash' => [
                'http://example.com/en/help',
                [
                    'match' => ['/help'],
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/help/',
                ],
            ],
            'pathWithTrailingSlashes' => [
                'http://example.com/en/help',
                [
                    'match' => ['/help'],
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/help////',
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
     * @dataProvider redirectPathProvider
     * @covers ::process()
     */
    public function testRedirectPath($expected, array $conf, array $server): void
    {
        $request = ServerRequestFactory::fromGlobals($server);
        $middleware = new I18nMiddleware($conf);
        $response = $middleware->process($request, $this->requestHandler);

        static::assertEquals(302, $response->getStatusCode());
        static::assertEquals($expected, $response->getHeaderLine('Location'));
    }

    /**
     * Data provider for `testSetupLocale()`
     *
     * @return array
     */
    public function setupLocaleProvider(): array
    {
        return [
            'useDefault' => [
                [
                    'lang' => 'en',
                    'locale' => 'en_US',
                ],
                [
                    'REQUEST_URI' => '/help',
                ],
            ],
            'notValidLang' => [
                [
                    'lang' => 'en',
                    'locale' => 'en_US',
                ],
                [
                    'REQUEST_URI' => '/es/help',
                ],
            ],
            'setLocaleByPath' => [
                [
                    'lang' => 'it',
                    'locale' => 'it_IT',
                ],
                [
                    'REQUEST_URI' => '/it/help',
                ],
            ],
        ];
    }

    /**
     * Test setup Locale method
     *
     * @param array $expected The expected values (locale and lang)
     * @param array $server The server vars
     * @return void
     * @dataProvider setupLocaleProvider
     * @covers ::detectLocale()
     * @covers ::readSession()
     * @covers ::setupLocale()
     */
    public function testSetupLocale(array $expected, array $server): void
    {
        $request = ServerRequestFactory::fromGlobals($server);
        $middleware = new I18nMiddleware();
        $middleware->process($request, $this->requestHandler);

        static::assertEquals($expected['locale'], I18n::getLocale());
        static::assertEquals($expected['lang'], Configure::read('I18n.lang'));
    }

    /**
     * Test that an exception is raised if missing required conf.
     *
     * @return void
     * @covers ::setupLocale()
     */
    public function testSetupLocaleMissingConfig(): void
    {
        $this->expectException(InternalErrorException::class);
        $this->expectExceptionCode(500);

        Configure::delete('I18n');
        $request = ServerRequestFactory::fromGlobals([
            'REQUEST_URI' => '/help',
        ]);
        $middleware = new I18nMiddleware();
        $middleware->process($request, $this->requestHandler);
    }

    /**
     * Test that if middleware is not configured properly the locale cookie is ignored.
     *
     * @return void
     * @covers ::detectLocale()
     * @covers ::setupLocale()
     * @covers ::getResponseWithCookie()
     */
    public function testNotUseCookie(): void
    {
        $cookieName = 'I18nLocale';
        $server = [
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/help',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
        ];
        $request = ServerRequestFactory::fromGlobals($server, null, null, [$cookieName => 'it_IT']);
        $middleware = new I18nMiddleware();
        $middleware->process($request, $this->requestHandler);

        static::assertEquals('en_US', I18n::getLocale());
    }

    /**
     * Test that if middleware is configured properly the locale is set by cookie
     *
     * @return void
     * @covers ::detectLocale()
     * @covers ::setupLocale()
     * @covers ::getResponseWithCookie()
     */
    public function testReadFromCookie(): void
    {
        $cookieName = 'I18nLocale';
        $server = [
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/help',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
        ];
        $request = ServerRequestFactory::fromGlobals($server, null, null, [$cookieName => 'it_IT']);
        $middleware = new I18nMiddleware([
            'cookie' => ['name' => $cookieName],
        ]);
        $response = $middleware->process($request, $this->requestHandler);

        static::assertEquals('it_IT', I18n::getLocale());
    }

    /**
     * Test cookie creation.
     *
     * @return void
     * @covers ::detectLocale()
     * @covers ::setupLocale()
     * @covers ::getResponseWithCookie()
     */
    public function testCreateCookie(): void
    {
        $cookieName = 'I18nLocale';
        $server = [
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/help',
            'HTTP_ACCEPT_LANGUAGE' => 'it-IT',
        ];
        $request = ServerRequestFactory::fromGlobals($server);
        $middleware = new I18nMiddleware([
            'cookie' => [
                'name' => $cookieName,
                'create' => true,
            ],
        ]);
        /** @var \Cake\Http\Response $response */
        $response = $middleware->process($request, $this->requestHandler);

        static::assertEquals('it_IT', I18n::getLocale());
        $cookie = $response->getCookieCollection()->get($cookieName);
        static::assertInstanceOf(Cookie::class, $cookie);
        static::assertEquals('it_IT', $cookie->getValue());

        $expireYear = (int)$cookie->getExpiry()->format('Y');
        $currentYear = (int)date('Y');
        static::assertEquals($currentYear + 1, $expireYear);
    }

    /**
     * Test change expire cookie.
     *
     * @return void
     * @covers ::detectLocale()
     * @covers ::setupLocale()
     * @covers ::getResponseWithCookie()
     */
    public function testChangeExpireCookie(): void
    {
        $cookieName = 'I18nLocale';
        $server = [
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/help',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US',
        ];
        $request = ServerRequestFactory::fromGlobals($server, null, null, [$cookieName => 'it_IT']);
        $expireExpected = '2050-12-31';
        $middleware = new I18nMiddleware([
            'cookie' => [
                'name' => $cookieName,
                'create' => true,
                'expire' => $expireExpected,
            ],
        ]);
        /** @var \Cake\Http\Response $response */
        $response = $middleware->process($request, $this->requestHandler);

        static::assertEquals('it_IT', I18n::getLocale());
        $cookie = $response->getCookieCollection()->get($cookieName);
        static::assertInstanceOf(Cookie::class, $cookie);
        static::assertEquals('it_IT', $cookie->getValue());
        static::assertEquals($expireExpected, $cookie->getExpiry()->format('Y-m-d'));
    }

    /**
     * Data provider for `testChangeLangAndRedirect()`
     *
     * @return array
     */
    public function changeLangProvider(): array
    {
        return [
            'ok' => [
                [
                    'location' => '/home',
                    'status' => 302,
                    'cookie' => 'it_IT',
                ],
                [
                    'cookie' => [
                        'name' => 'i18nLocal',
                        'create' => true,
                    ],
                    'switchLangUrl' => '/lang',
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/lang',
                    'HTTP_REFERER' => '/home',
                    'HTTP_ACCEPT_LANGUAGE' => 'en-US',
                ],
                [
                    'new' => 'it',
                ],
            ],
            'ok session' => [
                [
                    'location' => '/home',
                    'status' => 302,
                    'session' => 'it_IT',
                ],
                [
                    'sessionKey' => 'I18nSessionLocale',
                    'switchLangUrl' => '/lang',
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/lang',
                    'HTTP_REFERER' => '/home',
                    'HTTP_ACCEPT_LANGUAGE' => 'en-US',
                ],
                [
                    'new' => 'it',
                ],
            ],
            'ok cookie and session' => [
                [
                    'location' => '/home',
                    'status' => 302,
                    'cookie' => 'it_IT',
                ],
                [
                    'cookie' => [
                        'name' => 'i18nLocal',
                        'create' => true,
                    ],
                    'sessionKey' => 'I18nSessionLocale',
                    'switchLangUrl' => '/lang',
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/lang',
                    'HTTP_REFERER' => '/home',
                    'HTTP_ACCEPT_LANGUAGE' => 'en-US',
                ],
                [
                    'new' => 'it',
                ],
            ],
            'no query' => [
                new BadRequestException('Missing required "new" query string'),
                [
                    'cookie' => ['name' => 'i18nLocal'],
                    'switchLangUrl' => '/lang',
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/lang',
                ],
                [],
            ],
            'no lang' => [
                new BadRequestException('Lang "de" not supported'),
                [
                    'cookie' => ['name' => 'i18nLocal'],
                    'switchLangUrl' => '/lang',
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/lang',
                ],
                [
                    'new' => 'de',
                    'redirect' => '/home',
                ],
            ],
            'no cookie, no session' => [
                new \LogicException('I18nMiddleware misconfigured. `switchLangUrl` requires `cookie.name` or `sessionKey`'),
                [
                    'switchLangUrl' => '/lang',
                ],
                [
                    'HTTP_HOST' => 'example.com',
                    'REQUEST_URI' => '/lang',
                ],
                [],
            ],
        ];
    }

    /**
     * Test `changeLangAndRedirect()` method via URI and query string
     *
     * @param array|\Exception $expected Expected result
     * @param array $conf The configuration passed to middleware
     * @param array $server The server vars
     * @param array $query The query string
     * @return void
     * @dataProvider changeLangProvider
     * @covers ::changeLangAndRedirect()
     * @covers ::process()
     * @covers ::updateSession()
     * @covers ::getSessionKey()
     */
    public function testChangeLangAndRedirect($expected, $conf, $server, $query): void
    {
        if ($expected instanceof \Exception) {
            $this->expectException(get_class($expected));
            $this->expectExceptionCode($expected->getCode());
            $this->expectExceptionMessage($expected->getMessage());
        }

        $request = ServerRequestFactory::fromGlobals($server, $query);
        $middleware = new I18nMiddleware($conf);
        /** @var \Cake\Http\Response $response */
        $response = $middleware->process($request, $this->requestHandler);

        static::assertEquals($expected['status'], $response->getStatusCode());
        static::assertEquals($expected['location'], $response->getHeaderLine('Location'));

        $cookieName = Hash::get($conf, 'cookie.name');
        if ($cookieName) {
            $cookie = $response->getCookieCollection()->get($cookieName);
            static::assertInstanceOf(Cookie::class, $cookie);
            static::assertEquals($expected['cookie'], $cookie->getValue());
        }

        if (array_key_exists('session', $expected)) {
            static::assertEquals($expected['session'], $request->getSession()->read($conf['sessionKey']));
        }
    }

    /**
     * Test that the session was updated with locale detected from request.
     *
     * @return void
     * @covers ::process()
     * @covers ::updateSession()
     */
    public function testSessionFallback(): void
    {
        $sessionKey = 'I18nSessionLocale';
        $server = [
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/help',
            'HTTP_ACCEPT_LANGUAGE' => 'it-IT',
        ];
        $request = ServerRequestFactory::fromGlobals($server, null, null);

        static::assertNull($request->getSession()->read($sessionKey));
        $middleware = new I18nMiddleware(['sessionKey' => $sessionKey]);
        $middleware->process($request, $this->requestHandler);

        static::assertEquals('it_IT', I18n::getLocale());
        static::assertEquals('it_IT', $request->getSession()->read($sessionKey));
    }

    /**
     * Test that locale is set reading from session.
     *
     * @return void
     * @covers ::process()
     * @covers ::detectLocale()
     * @covers ::readSession()
     * @covers ::updateSession()
     * @covers ::getResponseWithCookie()
     */
    public function testGetLocaleFromSession(): void
    {
        $sessionKey = 'I18nSessionLocale';
        $server = [
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/help',
            'HTTP_ACCEPT_LANGUAGE' => 'en-EN',
        ];
        $request = ServerRequestFactory::fromGlobals($server, null, null);
        $request->getSession()->write($sessionKey, 'it_IT');

        $middleware = new I18nMiddleware(['sessionKey' => $sessionKey]);
        $middleware->process($request, $this->requestHandler);

        static::assertEquals('it_IT', I18n::getLocale());
        static::assertEquals('it_IT', $request->getSession()->read($sessionKey));
    }

    /**
     * Test that locale is set reading from cookie when cookie and session are both configured.
     *
     * @return void
     * @covers ::process()
     * @covers ::detectLocale()
     * @covers ::updateSession()
     * @covers ::getResponseWithCookie()
     */
    public function testGetLocaleFromCookie(): void
    {
        $cookieName = 'I18nCookieLocale';
        $sessionKey = 'I18nSessionLocale';
        $server = [
            'HTTP_HOST' => 'example.com',
            'REQUEST_URI' => '/help',
            'HTTP_ACCEPT_LANGUAGE' => 'en-EN',
        ];
        $request = ServerRequestFactory::fromGlobals($server, null, null, [$cookieName => 'it_IT']);
        $request->getSession()->write($sessionKey, 'en_EN');

        $middleware = new I18nMiddleware([
            'sessionKey' => $sessionKey,
            'cookie' => [
                'name' => $cookieName,
                'create' => true,
            ],
        ]);
        /** @var \Cake\Http\Response $response */
        $response = $middleware->process($request, $this->requestHandler);

        static::assertEquals('it_IT', I18n::getLocale());
        static::assertEquals('it_IT', $request->getSession()->read($sessionKey)); // session is updated
        static::assertEquals('it_IT', $response->getCookieCollection()->get($cookieName)->getValue()); // session is updated
    }
}
