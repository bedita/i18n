<?php
declare(strict_types=1);

/**
 * BEdita, API-first content management framework
 * Copyright 2019 ChannelWeb Srl, Chialab Srl
 *
 * This file is part of BEdita: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See LICENSE.LGPL or <http://gnu.org/licenses/lgpl-3.0.html> for more details.
 */
namespace BEdita\I18n\Test\View\Helper;

use BEdita\I18n\View\Helper\I18nHelper;
use Cake\Core\Configure;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test class for I18nHelper
 */
class I18nHelperTest extends TestCase
{
    /**
     * The instance of `BEdita\I18n\View\Helper\I18nHelper`
     *
     * @var \BEdita\I18n\View\Helper\I18nHelper|null
     */
    protected ?I18nHelper $I18n = null;

    /**
     * Test view
     *
     * @var \Cake\View\View|null
     */
    protected ?View $View = null;

    /**
     * Test `object`
     *
     * @var array
     */
    protected array $object = [
        'id' => 999,
        'attributes' => [
            'title' => 'Sample',
            'description' => 'A dummy example',
            'lang' => 'en',
        ],
    ];

    /**
     * Test `included`
     *
     * @var array
     */
    protected array $included = [
        [
            'id' => 99999,
            'type' => 'translations',
            'attributes' => [
                'object_id' => 999,
                'lang' => 'it',
                'translated_fields' => [
                    'title' => 'Esempio',
                ],
            ],
        ],
    ];

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->View = new View();
        $this->I18n = new I18nHelper($this->View);

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

            // usually set by middleware
            'lang' => 'en',
        ]);

        $routeBuilder = Router::createRouteBuilder('/');
        $routeBuilder->connect(
            '/test',
            ['controller' => 'TestApp', 'action' => 'test'],
            ['_name' => 'test'],
        );
        Router::setRouteCollection(Router::getRouteCollection());
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->I18n);
        Configure::delete('I18n');
        Router::reload();
    }

    /**
     * Data provider for `testChangeUrlLang()`
     *
     * @return array
     */
    public static function changeUrlLangProvider(): array
    {
        return [
            'noChange' => [
                '/help',
                [
                    'REQUEST_URI' => '/help',
                ],
                'en',
            ],
            'noChangeWithQueryString' => [
                '/help?page=1',
                [
                    'REQUEST_URI' => '/help',
                    'QUERY_STRING' => 'page=1',
                ],
                'en',
            ],
            'change' => [
                '/it/help',
                [
                    'REQUEST_URI' => '/en/help',
                ],
                'it',
            ],
            'query' => [
                '/it/help?page=1',
                [
                    'REQUEST_URI' => '/en/help',
                    'QUERY_STRING' => 'page=1',
                ],
                'it',
            ],
            'empty request' => [
                '',
                [
                ],
                'en',
            ],
            'lang url' => [
                '/lang?new=en',
                [
                    'REQUEST_URI' => '/help',
                ],
                'en',
                '/lang',
            ],
        ];
    }

    /**
     * Test the url returned changing lang
     *
     * @param string $expected The string expected
     * @param array $server The server vars
     * @param string $lang The language to change
     * @param string|null $switchUrl The switch language url
     * @return void
     */
    #[DataProvider('changeUrlLangProvider')]
    public function testChangeUrlLang(string $expected, array $server, string $lang, ?string $switchUrl = null): void
    {
        if (!empty($server)) {
            $request = ServerRequestFactory::fromGlobals($server);
            $method = method_exists(Router::class, 'setRequest') ? 'setRequest' : 'pushRequest';
            Router::$method($request);
        }

        static::assertEquals($expected, $this->I18n->changeUrlLang($lang, $switchUrl));
    }

    /**
     * Data provider for `testField()`
     *
     * @return array
     */
    public static function fieldProvider(): array
    {
        $objectStructured = [
            'id' => 999,
            'attributes' => [
                'title' => 'Sample',
                'description' => 'A dummy example',
                'lang' => 'en',
            ],
        ];
        $objectBase = (array)$objectStructured['attributes'];
        $objectBase['id'] = $objectStructured['id'];
        $included = [
            [
                'id' => 99999,
                'type' => 'translations',
                'attributes' => [
                    'object_id' => 999,
                    'lang' => 'it',
                    'translated_fields' => [
                        'title' => 'Esempio',
                    ],
                ],
            ],
        ];

        return [
            'empty object' => [
                [], // object
                'title', // attribute
                'it', // lang
                true, // defaultNull
                [], // included
                null, // expected
            ],
            'translation found / object base' => [
                $objectBase, // object
                'title', // attribute
                'it', // lang
                false, // defaultNull
                $included, // included
                'Esempio', // expected
            ],
            'translation found / object structured' => [
                $objectStructured, // object
                'title', // attribute
                'it', // lang
                false, // defaultNull
                $included, // included
                'Esempio', // expected
            ],
            'translation missing: default null false' => [
                $objectBase, // object
                'description', // attribute
                'it', // lang
                false, // defaultNull
                $included, // included
                'A dummy example', // expected
            ],
            'translation missing: default null true' => [
                $objectBase, // object
                'description', // attribute
                'it', // lang
                true, // defaultNull
                $included, // included
                null, // expected
            ],
        ];
    }

    /**
     * Test `field(array $object, array $included, string $attribute, string $lang, bool $defaultNull = false)` method
     *
     * @param array $object The object to translate
     * @param string $attribute The attribute to translate
     * @param string $lang The language of translation, 2 chars code
     * @param bool $defaultNull True if default value should be null; otherwise on missing translation, original field value
     * @param array $included The included translations data
     * @param string|null $expected The expected translation
     * @return void
     */
    #[DataProvider('fieldProvider')]
    public function testField(array $object, string $attribute, string $lang, bool $defaultNull, array $included, ?string $expected): void
    {
        $actual = $this->I18n->field($object, $attribute, $lang, $defaultNull, $included);
        static::assertEquals($expected, $actual);
    }

    /**
     * Test `field(array $object, array $included, string $attribute, string $lang, bool $defaultNull = false)` method
     *
     * @return void
     */
    public function testFieldIncludedFromView(): void
    {
        $this->View->set('included', $this->included);
        $actual = $this->I18n->field($this->object, 'title', 'it', false, []);
        static::assertEquals('Esempio', $actual);
    }

    /**
     * Test `field` method with embedded relationships
     *
     * @return void
     */
    public function testFieldEmbedded(): void
    {
        $object = [
            'attributes' => [
                'description' => 'una descrizione',
                'lang' => 'it',
            ],
            'relationships' => [
                'translations' => [
                    'data' => [
                        [
                            'attributes' => [
                                'lang' => 'en',
                                'status' => 'on',
                                'translated_fields' => [
                                    'description' => 'a description',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $actual = $this->I18n->field($object, 'description', 'en');
        static::assertEquals('a description', $actual);
        // 'fr' is not found, fallback to original
        $actual = $this->I18n->field($object, 'description', 'fr');
        static::assertEquals('una descrizione', $actual);
    }

    /**
     * Data provider for `testExists()`
     *
     * @return array
     */
    public static function existsProvider(): array
    {
        $object = [
            'id' => 999,
            'attributes' => [
                'title' => 'Sample',
                'description' => 'A dummy example',
                'lang' => 'en',
            ],
        ];
        $included = [
            [
                'id' => 99999,
                'type' => 'translations',
                'attributes' => [
                    'object_id' => 999,
                    'lang' => 'it',
                    'translated_fields' => [
                        'title' => 'Esempio',
                    ],
                ],
            ],
        ];

        return [
            'empty object' => [
                [], // object
                'title', // attribute
                'it', // lang
                [], // included
                false, // expected
            ],
            'translation found' => [
                $object, // object
                'title', // attribute
                'it', // lang
                $included, // included
                true, // expected
            ],
            'translation missing' => [
                $object, // object
                'description', // attribute
                'it', // lang
                $included, // included
                false, // expected
            ],
            'lang null' => [
                $object, // object
                'description', // attribute
                null, // lang
                $included, // included
                false, // expected
            ],
        ];
    }

    /**
     * Test `exists(array $object, array $included, string $attribute, string $lang)` method
     *
     * @param array $object The object to translate
     * @param string $attribute The attribute to translate
     * @param string|null $lang The language of translation, 2 chars code
     * @param array|null $included The included translations data
     * @param bool $expected The expected result (true => exists, false => does not exist)
     * @return void
     */
    #[DataProvider('existsProvider')]
    public function testExists(array $object, string $attribute, ?string $lang, ?array $included, bool $expected): void
    {
        if ($lang == null) {
            Configure::write('I18n.lang', 'it');
        }
        $actual = $this->I18n->exists($object, $attribute, $lang, $included);
        static::assertEquals($expected, $actual);
    }

    /**
     * Test `exists` method
     *
     * @return void
     */
    public function testExistsIncludedFromView(): void
    {
        $this->View->set('included', $this->included);
        $included = [];
        $actual = $this->I18n->exists($this->object, 'title', 'it', $included);
        static::assertTrue($actual);
    }

    /**
     * Test `exists()` method
     *
     * @return void
     */
    public function testDefaultExists(): void
    {
        Configure::write('I18n.lang', 'it');

        $actual = $this->I18n->exists($this->object, 'title');
        static::assertFalse($actual);
    }

    /**
     * Test internal translation cache
     *
     * @return void
     */
    public function testCache(): void
    {
        Configure::write('I18n.lang', 'it');

        $actual = $this->I18n->field($this->object, 'title', null, false, $this->included);
        static::assertEquals('Esempio', $actual);

        // use cache
        $actual = $this->I18n->field($this->object, 'title');
        static::assertEquals('Esempio', $actual);
    }

    /**
     * Test `reset()` method
     *
     * @return void
     */
    public function testCacheReset(): void
    {
        Configure::write('I18n.lang', 'it');

        $actual = $this->I18n->field($this->object, 'title', null, false, $this->included);
        static::assertEquals('Esempio', $actual);

        // reset cache
        $this->I18n->reset();
        $actual = $this->I18n->field($this->object, 'title');
        static::assertEquals('Sample', $actual);
    }

    /**
     * Data provider for `testMetaHreflang()`
     *
     * @return array
     */
    public static function metaHreflangProvider(): array
    {
        return [
            'empty' => [
                '',
                [
                    'REQUEST_URI' => '/help',
                ],
            ],
            'meta' => [
                '<link href="http://localhost/en/help" rel="alternate" hreflang="en"><link href="http://localhost/it/help" rel="alternate" hreflang="it">',
                [
                    'REQUEST_URI' => '/en/help',
                    'PHP_SELF' => '/',
                ],
            ],
        ];
    }

    /**
     * Test meta tag hreflang.
     *
     * @param string $expected The expected output.
     * @param array $server Request configuration.
     * @return void
     */
    #[DataProvider('metaHreflangProvider')]
    public function testMetaHreflang(string $expected, array $server): void
    {
        $request = ServerRequestFactory::fromGlobals($server);
        $method = method_exists(Router::class, 'setRequest') ? 'setRequest' : 'pushRequest';
        Router::$method($request);

        $meta = $this->I18n->metaHreflang();
        static::assertEquals($expected, $meta);
    }

    /**
     * Test that `metaHreflang()` returns an empty string if no request was set.
     *
     * @return void
     */
    public function testMetaHreflangMissingRequest(): void
    {
        static::assertEquals('', $this->I18n->metaHreflang());
    }

    /**
     * Data provider for `testBuildUrl()`
     *
     * @return array
     */
    public static function buildUrlProvider(): array
    {
        return [
            'default' => [
                '/en/some/path',
                '/some/path',
            ],
            'fr' => [
                '/fr/some/path',
                '/some/path',
                'fr',
            ],
            'unchanged' => [
                '/de/some/path',
                '/de/some/path',
                'de',
            ],
            'namedurl' => [
                '/test',
                ['_name' => 'test'],
            ],
        ];
    }

    /**
     * Test `buildUrl` method.
     *
     * @param string $expected The expected output.
     * @param string $path URL path.
     * @param string $lang Current lang code.
     * @return void
     */
    #[DataProvider('buildUrlProvider')]
    public function testBuildUrl(string $expected, mixed $path, string $lang = 'en'): void
    {
        Configure::write('I18n.lang', $lang);

        $url = $this->I18n->buildUrl($path);
        static::assertEquals($expected, $url);
    }
}
