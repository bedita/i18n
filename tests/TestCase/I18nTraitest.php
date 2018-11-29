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
namespace BEdita\I18n\Test;

use BEdita\I18n\I18nTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

/**
 * {@see \BEdita\I18n\I18nTrait} Test Case
 *
 * @coversDefaultClass \BEdita\I18n\I18nTrait
 */
class I18nTraitTest extends TestCase
{

    /**
     * The object using trait
     *
     * @var object
     */
    protected $subject = null;

    /**
     * {@inheritDoc}
     */
    public function setUp() : void
    {
        parent::setUp();

        $this->subject = $this->getObjectForTrait(I18nTrait::class);

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

        $this->subject = null;
        Configure::delete('I18n');
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
        static::assertEquals($expected, $this->subject->getLangName($lang));
    }
}
