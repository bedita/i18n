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
namespace BEdita\I18n\Test\Routing\Route;

use BEdita\I18n\Core\I18nTrait;
use BEdita\I18n\Routing\Route\I18nRoute;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test class for I18nRoute
 */
class I18nRouteTest extends TestCase
{
    use I18nTrait;

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

            // usually set by middleware
            'lang' => 'it',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        parent::tearDown();

        Configure::delete('I18n');
    }

    /**
     * Data provider for testBuildTemplate
     *
     * @return array
     */
    public static function templateProvider(): array
    {
        return [
            'root' => [
                '/{lang}',
                '/',
            ],
            'rootLang' => [
                '/{lang}',
                '/{lang}',
            ],
            'rootLangColon' => [
                '/:lang',
                '/:lang',
            ],
            'simplePath' => [
                '/{lang}/simple/path',
                '/simple/path',
            ],
            'simplePath2' => [
                '/{lang}/{controller}/{action}',
                '/{controller}/{action}',
            ],
            'simplePath2Colon' => [
                '/:lang/:controller/:action',
                '/:controller/:action',
            ],
            'pathStartsWithLang' => [
                '/{lang}/path/here',
                '/{lang}/path/here',
            ],
            'pathStartsWithLangColon' => [
                '/:lang/path/here',
                '/:lang/path/here',
            ],
            'pathContainLangLike' => [
                '/{lang}/{language}/path/here',
                '/{language}/path/here',
            ],
            'pathContainLangLikeColon' => [
                '/:lang/:language/path/here',
                '/:language/path/here',
            ],
            'pathWithLangInside' => [
                '/{controller}/{lang}/other',
                '/{controller}/{lang}/other',
            ],
            'pathWithLangInsideColon' => [
                '/:controller/:lang/other',
                '/:controller/:lang/other',
            ],
        ];
    }

    /**
     * Test buildTemplate method
     *
     * @param string $expected The expected template for the route
     * @param string $template The initial template
     * @return void
     */
    #[DataProvider('templateProvider')]
    public function testBuildTemplate(string $expected, string $template): void
    {
        $route = new I18nRoute($template);
        static::assertEquals($expected, $route->template);
    }

    /**
     * Data provider for testLangPatterns
     *
     * @return array
     */
    public static function langPatternsProvider(): array
    {
        return [
            'noOptions' => [
                'en|it',
                [],
            ],
            'langPatterns' => [
                'sp|fr',
                [
                    'lang' => 'sp|fr',
                ],
            ],
        ];
    }

    /**
     * Test that `lang` pattern is rightly set.
     *
     * @param string $expected The expected pattern for 'lang'
     * @param array $options The options for constructor.
     * @return void
     */
    #[DataProvider('langPatternsProvider')]
    public function testLangPatterns(string $expected, array $options): void
    {
        $route = new I18nRoute('/', [], $options);
        static::assertEquals($expected, $route->options['lang']);
    }

    /**
     * Test that if missing 'lang' param match() method return current lang in path.
     *
     * @return void
     */
    public function testMatchUseCurrentLang(): void
    {
        $route = new I18nRoute(
            '/gustavo/help',
            ['controller' => 'GustavoSupporto', 'action' => 'help'],
        );

        $result = $route->match([
            'controller' => 'GustavoSupporto',
            'action' => 'help',
        ]);

        $this->assertEquals(sprintf('/%s/gustavo/help', $this->getLang()), $result);
    }

    /**
     * Test that passing a different lang match() method return it in path
     *
     * @return void
     */
    public function testMatchUseCustomLang(): void
    {
        $route = new I18nRoute(
            '/gustavo/help',
            ['controller' => 'GustavoSupporto', 'action' => 'help'],
        );

        $result = $route->match([
            'controller' => 'GustavoSupporto',
            'action' => 'help',
            'lang' => 'en',
        ]);

        $this->assertEquals('/en/gustavo/help', $result);
        $this->assertNotEquals('en', $this->getLang());
    }

    /**
     * Test that passing an invalid lang match() method return false
     *
     * @return void
     */
    public function testMatchInvalidLang(): void
    {
        $route = new I18nRoute(
            '/gustavo/help',
            ['controller' => 'GustavoSupporto', 'action' => 'help'],
        );

        $result = $route->match([
            'controller' => 'GustavoSupporto',
            'action' => 'help',
            'lang' => 'sp',
        ]);

        $this->assertNull($result);
    }
}
