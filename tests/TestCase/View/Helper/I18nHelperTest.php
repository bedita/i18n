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
namespace BEdita\I18n\Test\View\Helper;

use BEdita\I18n\View\Helper\I18nHelper;
use Cake\Core\Configure;
use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Cake\View\View;

/**
 * {@see \BEdita\I18n\View\Helper\I18nHelper} Test Case
 *
 * @coversDefaultClass \BEdita\I18n\View\Helper\I18nHelper
 */
class I18nHelperTest extends TestCase
{

    /**
     * The instance of `BEdita\I18n\View\Helper\I18nHelper`
     *
     * @var \BEdita\I18n\View\Helper\I18nHelper
     */
    protected $I18n = null;

    /**
     * Test `object`
     *
     * @var array
     */
    protected $object = [
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
    protected $included = [
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
     * {@inheritDoc}
     */
    public function setUp() : void
    {
        parent::setUp();

        $this->I18n = new I18nHelper(new View());

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
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown() : void
    {
        parent::tearDown();

        $this->I18n = null;
    }

    /**
     * Data provider for `testGetLangName()`
     *
     * @return array
     */
    public function getLangNameProvider() : array
    {
        return [
            'default' => [
                'English',
                null,
            ],
            'existingLang' => [
                'Italiano',
                'it',
            ],
            'notConfiguredLang' => [
                null,
                'es',
            ],
        ];
    }

    /**
     * Test `getLangName()`
     *
     * @return void
     *
     * @dataProvider getLangNameProvider
     * @covers ::getLangName()
     */
    public function testGetLangName($expected, $lang) : void
    {
        static::assertEquals($expected, $this->I18n->getLangName($lang));
    }

    /**
     * Data provider for `testChangeUrlLang()`
     *
     * @return array
     */
    public function changeUrlLangProvider() : array
    {
        return [
            'noChange' => [
                '/help',
                [
                    'REQUEST_URI' => '/help',
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
        ];
    }

    /**
     * Test the url returned changing lang
     *
     * @param string $expected The string expected
     * @param array $server The server vars
     * @param string $lang The language to change
     * @return void
     *
     * @dataProvider changeUrlLangProvider
     * @covers ::changeUrlLang()
     */
    public function testChangeUrlLang($expected, array $server, $lang) : void
    {
        $request = ServerRequestFactory::fromGlobals($server);
        Router::pushRequest($request);

        static::assertEquals($expected, $this->I18n->changeUrlLang($lang));
    }

    /**
     * Data provider for `testField()`
     *
     * @return array
     */
    public function fieldProvider() : array
    {
        $objectBase = ['id' => $this->object['id']] + $this->object['attributes'];
        $objectStructured = $this->object;

        $included = $this->included;

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
     * @dataProvider fieldProvider()
     * @covers ::field()
     *
     * @param array $object The object to translate
     * @param string $attribute The attribute to translate
     * @param string $lang The language of translation, 2 chars code
     * @param boolean $defaultNull True if default value should be null; otherwise on missing translation, original field value
     * @param array $included The included translations data
     * @param string|null $expected The expected translation
     * @return void
     */
    public function testField(array $object, string $attribute, string $lang, bool $defaultNull, array $included, ?string $expected) : void
    {
        $actual = $this->I18n->field($object, $attribute, $lang, $defaultNull, $included);
        static::assertEquals($expected, $actual);
    }

    /**
     * Data provider for `testExists()`
     *
     * @return array
     */
    public function existsProvider() : array
    {
        $object = $this->object;
        $included = $this->included;

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
     * @dataProvider existsProvider()
     * @covers ::exists()
     * @covers ::getTranslatedField()
     *
     * @param array $object The object to translate
     * @param string $attribute The attribute to translate
     * @param string|null $lang The language of translation, 2 chars code
     * @param array|null $included The included translations data
     * @param bool $expected The expected result (true => exists, false => does not exist)
     * @return void
     */
    public function testExists(array $object, string $attribute, ?string $lang, ?array $included, bool $expected) : void
    {
        if ($lang == null) {
            Configure::write('I18n.lang', 'it');
        }
        $actual = $this->I18n->exists($object, $attribute, $lang, $included);
        static::assertEquals($expected, $actual);
    }

    /**
     * Test `exists()` method
     *
     * @covers ::exists()
     * @covers ::getTranslatedField()
     *
     * @return void
     */
    public function testDefaultExists() : void
    {
        Configure::write('I18n.lang', 'it');

        $actual = $this->I18n->exists($this->object, 'title');
        static::assertFalse($actual);
    }

    /**
     * Test internal translation cache
     *
     * @covers ::field()
     * @covers ::getTranslatedField()
     *
     * @return void
     */
    public function testCache() : void
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
     * @covers ::reset()
     * @covers ::getTranslatedField()
     *
     * @return void
     */
    public function testCacheReset() : void
    {
        Configure::write('I18n.lang', 'it');

        $actual = $this->I18n->field($this->object, 'title', null, false, $this->included);
        static::assertEquals('Esempio', $actual);

        // reset cache
        $this->I18n->reset();
        $actual = $this->I18n->field($this->object, 'title');
        static::assertEquals('Sample', $actual);
    }
}
