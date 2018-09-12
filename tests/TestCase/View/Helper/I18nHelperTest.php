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
        $data = [
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
                    'lang' => 'it',
                    'translated_fields' => [
                        'title' => 'Esempio',
                    ],
                ],
            ],
        ];

        return [
            'empty response' => [
                [], // response
                'title', // attribute
                'it', // lang
                true, // defaultNull
                null, // expected
            ],
            'translation found' => [
                compact('data') + compact('included'), // response
                'title', // attribute
                'it', // lang
                false, // defaultNull
                'Esempio', // expected
            ],
            'translation missing: default null false' => [
                compact('data') + compact('included'), // response
                'description', // attribute
                'it', // lang
                false, // defaultNull
                'A dummy example', // expected
            ],
            'translation missing: default null true' => [
                compact('data') + compact('included'), // response
                'description', // attribute
                'it', // lang
                true, // defaultNull
                null, // expected
            ],
        ];
    }

    /**
     * Test `field(array $response, string $attribute, string $lang, bool $defaultNull = false)` method
     *
     * @dataProvider fieldProvider()
     * @covers ::field()
     *
     * @param array $response The response representing the resource data
     * @param string $attribute The attribute to translate
     * @param string $lang The language of translation, 2 chars code
     * @param boolean $defaultNull True if default value should be null; otherwise on missing translation, original field value
     * @param string|null $expected The expected translation
     * @return void
     */
    public function testField(array $response, string $attribute, string $lang, bool $defaultNull, ?string $expected) : void
    {
        $actual = $this->I18n->field($response, $attribute, $lang, $defaultNull);
        static::assertEquals($expected, $actual);
    }

    /**
     * Data provider for `testExists()`
     *
     * @return array
     */
    public function existsProvider() : array
    {
        $data = [
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
                    'lang' => 'it',
                    'translated_fields' => [
                        'title' => 'Esempio',
                    ],
                ],
            ],
        ];

        return [
            'empty response' => [
                [], // response
                'title', // attribute
                'it', // lang
                false, // expected
            ],
            'translation found' => [
                compact('data') + compact('included'), // response
                'title', // attribute
                'it', // lang
                true, // expected
            ],
            'translation missing' => [
                compact('data') + compact('included'), // response
                'description', // attribute
                'it', // lang
                false, // expected
            ],
        ];
    }

    /**
     * Test `exists(array $response, string $attribute, string $lang)` method
     *
     * @dataProvider existsProvider()
     * @covers ::exists()
     * @covers ::getTranslatedField()
     *
     * @param array $response The response representing the resource data
     * @param string $attribute The attribute to translate
     * @param string $lang The language of translation, 2 chars code
     * @param bool $expected The expected result (true => exists, false => does not exist)
     * @return void
     */
    public function testExists(array $response, string $attribute, string $lang, bool $expected) : void
    {
        $actual = $this->I18n->exists($response, $attribute, $lang);
        static::assertEquals($expected, $actual);
    }
}
