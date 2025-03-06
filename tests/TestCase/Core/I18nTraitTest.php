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
namespace BEdita\I18n\Test\Core;

use BEdita\I18n\Core\I18nTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test class for I18nTrait
 */
class I18nTraitTest extends TestCase
{
    use I18nTrait;

    /**
     * The object using trait
     *
     * @var object|null
     */
    protected ?object $subject = null;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->subject = new class {
            use I18nTrait;
        };

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
     * @inheritDoc
     */
    public function tearDown(): void
    {
        parent::tearDown();

        unset($this->subject);
        Configure::delete('I18n');
    }

    /**
     * Data provider for `testGetLangName()`
     *
     * @return array
     */
    public static function getLangNameProvider(): array
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
     * @param string|null $expected The expected lang
     * @param string|null $lang The lang in input
     * @return void
     */
    #[DataProvider('getLangNameProvider')]
    public function testGetLangName($expected, $lang): void
    {
        $result = $this->subject->getLangName($lang);
        static::assertEquals($expected, $result);
    }
}
