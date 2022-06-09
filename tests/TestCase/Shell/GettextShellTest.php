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

namespace BEdita\I18n\Test\Shell;

use BEdita\I18n\Shell\GettextShell;
use Cake\Core\App;
use Cake\Core\Configure;
use Cake\TestSuite\ConsoleIntegrationTestCase;
use Cake\View\View;

/**
 * {@see \BEdita\I18n\Shell\GettextShell} Test Case
 *
 * @coversDefaultClass \BEdita\I18n\Shell\GettextShell
 */
class GettextShellTest extends ConsoleIntegrationTestCase
{
    /**
     * The shell for test
     *
     * @var \BEdita\I18n\Shell\GettextShell
     */
    protected $shell = null;

    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        Configure::write('I18n', [
            'locales' => [
                'en_US' => 'en',
                'it_IT' => 'it',
            ],
        ]);

        $this->shell = new GettextShell();
        parent::setUp();
        $this->cleanFiles();
    }

    /**
     * @inheritDoc
     */
    public function tearDown(): void
    {
        $this->cleanFiles();
        unset($this->shell);
        parent::tearDown();
    }

    /**
     * Test update and private methods called inside update
     *
     * @return void
     * @covers ::update()
     * @covers ::getPoResult()
     * @covers ::getTemplatePaths()
     * @covers ::getLocalePath()
     * @covers ::getPoName()
     */
    public function testUpdate(): void
    {
        $this->shell->params['app'] = sprintf('%s/tests/test_app/TestApp', getcwd());

        // set localePath using reflection class
        $localePath = sprintf('%s/tests/test_app/TestApp/Locale', getcwd());
        $reflection = new \ReflectionProperty(get_class($this->shell), 'localePath');
        $reflection->setAccessible(true);
        $reflection->setValue($this->shell, $localePath);

        // call the method
        $this->shell->update();

        // check po files are not empty
        foreach (['en_US', 'it_IT'] as $locale) {
            $content = file_get_contents(sprintf('%s/%s/default.po', $localePath, $locale));
            static::assertNotEmpty($content);
        }
        static::assertTrue(gettype($this->shell->getPoResult()) === 'array');
        static::assertTrue(gettype($this->shell->getTemplatePaths()) === 'array');
        static::assertTrue(gettype($this->shell->getLocalePath()) === 'string');
        static::assertTrue(gettype($this->shell->getPoName()) === 'string');
    }

    /**
     * Provider for 'setupPaths'
     *
     * @return array
     */
    public function setupPathsProvider(): array
    {
        $base = getcwd();

        return [
            'app' => [
                'tests/test_app/TestApp', // app path
                null, // start path
                null, // plugin path
                [
                    sprintf('%s/tests/test_app/TestApp/src', $base),
                    sprintf('%s/tests/test_app/TestApp/config', $base),
                    sprintf('%s/tests/test_app/TestApp/Template', $base),
                ], // template paths
                sprintf('%s/tests/test_app/TestApp/Locale', $base), // locale path
            ],
            'plugin' => [
                null, // app path
                sprintf('%s/tests/test_app/TestApp', $base), // start path
                'Dummy', // plugin name
                [
                    sprintf('%s/tests/test_app/plugins/Dummy/src', $base),
                    sprintf('%s/tests/test_app/plugins/Dummy/config', $base),
                ], // template paths
                sprintf('%s/tests/test_app/plugins/Dummy/Locale', $base), // locale path
            ],
        ];
    }

    /**
     * Test `setupPaths`
     *
     * @param string|null $appPath The app file path
     * @param string|null $startPath The start path
     * @param string|null $pluginName The plugin name
     * @param array $expectedTemplatePaths The expected template paths
     * @param string $expectedLocalePath The expected locale path
     * @return void
     * @dataProvider setupPathsProvider
     * @covers ::setupPaths()
     */
    public function testSetupPaths($appPath, $startPath, $pluginName, array $expectedTemplatePaths, string $expectedLocalePath): void
    {
        $expectedPoName = 'default.po';
        if (!empty($appPath)) {
            $this->shell->params['app'] = sprintf('%s/%s', getcwd(), $appPath);
        }
        if (!empty($startPath)) {
            $this->shell->params['startPath'] = $startPath;
        }
        if (!empty($pluginName)) {
            $this->loadPlugins([$pluginName]);
            $this->shell->params['plugin'] = $pluginName;
            $expectedPoName = sprintf('%s.po', $pluginName);
            $expectedTemplatePaths = array_merge($expectedTemplatePaths, App::path(View::NAME_TEMPLATE, $pluginName));
        }
        $method = self::getMethod('setupPaths');
        $method->invokeArgs($this->shell, []);
        $i = 0;
        $actualPaths = $this->shell->getTemplatePaths();
        foreach ($actualPaths as &$actual) {
            if (strlen($actual) !== strlen($expectedTemplatePaths[$i++])) {
                $actual = substr($actual, 0, -1);
            }
        }
        static::assertEquals($expectedTemplatePaths, $actualPaths);
        $actual = $this->shell->getLocalePath();
        if (strlen($actual) !== strlen($expectedLocalePath)) {
            $actual = substr($actual, 0, -1);
        }
        static::assertEquals($expectedLocalePath, $actual);
        static::assertEquals($expectedPoName, $this->shell->getPoName());
    }

    /**
     * Test writeMasterPot
     *
     * @covers ::writeMasterPot()
     * @return void
     */
    public function testWriteMasterPot(): void
    {
        // set localePath using reflection class
        $localePath = sprintf('%s/tests/test_app/TestApp/Locale', getcwd());
        $reflection = new \ReflectionProperty(get_class($this->shell), 'localePath');
        $reflection->setAccessible(true);
        $reflection->setValue($this->shell, $localePath);

        // set poResult using reflection class
        $poResult = [
            'This is a php sample',
            'A php content',
            'A php string with \"double quotes\"',
            "A php string with \'single quotes\'",
            'This is a twig sample',
            'A twig content',
            'A twig string with \"double quotes\"',
            "A twig string with \'single quotes\'",
        ];
        $reflection = new \ReflectionProperty(get_class($this->shell), 'poResult');
        $reflection->setAccessible(true);
        $reflection->setValue($this->shell, $poResult);

        // call writeMasterPot using reflection class
        $class = new \ReflectionClass('BEdita\I18n\Shell\GettextShell');
        $method = $class->getMethod('writeMasterPot');
        $method->setAccessible(true);
        $method->invokeArgs($this->shell, []);

        // file master.pot have been override, check again content (it should be unchanged), except for POT-Creation-Date
        $content = file_get_contents(sprintf('%s/master.pot', $localePath));
        static::assertNotEmpty($content);
    }

    /**
     * Test 'writePoFiles'
     *
     * @return void
     * @covers ::header()
     * @covers ::writePoFiles()
     * @covers ::analyzePoFile()
     */
    public function testWritePoFiles(): void
    {
        // set localePath using reflection class
        $localePath = sprintf('%s/tests/test_app/TestApp/Locale', getcwd());
        $reflection = new \ReflectionProperty(get_class($this->shell), 'localePath');
        $reflection->setAccessible(true);
        $reflection->setValue($this->shell, $localePath);

        // invoke writePoFiles
        $method = self::getMethod('writePoFiles');
        $method->invokeArgs($this->shell, []);

        // check po files are not empty
        foreach (['en_US', 'it_IT'] as $locale) {
            $content = file_get_contents(sprintf('%s/%s/default.po', $localePath, $locale));
            static::assertNotEmpty($content);
        }
    }

    /**
     * Provider for 'fixString'
     *
     * @return array
     */
    public function fixStringProvider()
    {
        return [
            'quotes' => [
                'something "quoted"', // input
                'something \"quoted\"', // expected
            ],
            'escaped quotes |||||' => [
                'something |||||quoted|||||', // input
                "something 'quoted'", // expected
            ],
            'new lines' => [
                sprintf('something%swith%snew%slines', "\n", "\n", "\n"), // input
                sprintf('something%swith%snew%slines', '\n', '\n', '\n'), // expected
            ],
        ];
    }

    /**
     * Test fixString
     *
     * @param string $input The input string
     * @param string $expected The expected output string
     * @return void
     * @dataProvider fixStringProvider
     * @covers ::fixString()
     */
    public function testFixString($input, $expected): void
    {
        $method = self::getMethod('fixString');
        $args = [ $input ];
        $result = $method->invokeArgs($this->shell, $args);
        static::assertEquals($expected, $result);
    }

    /**
     * Provider for 'testParseFile'
     *
     * @return array
     */
    public function parseFileProvider(): array
    {
        return [
            'no twig, no php extension file' => [
                sprintf('%s/tests/files/gettext/contents/sample.js', getcwd()),
                'js',
                [],
            ],
            'empty php file' => [
                sprintf('%s/tests/files/gettext/contents/empty.php', getcwd()),
                'php',
                [],
            ],
            'sample php' => [
                sprintf('%s/tests/files/gettext/contents/sample.php', getcwd()),
                'php',
                [
                    'default' => [
                        'This is a php sample',
                        'A php content',
                        'A php string with \"double quotes\"',
                        'A php string with \'single quotes\'',
                        '1 test __',
                        '2 test __',
                        '3 test __',
                        '4 test __',
                        '1 test __n',
                        '1 test __x',
                        '1 test __xn',
                        '1 test __dx',
                        '1 test __dxn',
                    ],
                    'DomainSampleD' => [
                        '1 test __d',
                    ],
                    'DomainSampleDN' => [
                        '1 test __dn',
                    ],
                ],
            ],
            'sample twig' => [
                sprintf('%s/tests/files/gettext/contents/sample.twig', getcwd()),
                'twig',
                [
                    'default' => [
                        'This is a twig sample',
                        'A twig content',
                        'A twig string with \"double quotes\"',
                        'A twig string with \'single quotes\'',
                    ],
                    'DomainSampleD' => [
                        'A twig string in a domain',
                    ],
                ],
            ],
        ];
    }

    /**
     * Test 'parseFile'
     *
     * @param string $file The file to parse
     * @param string $extension The file extension
     * @param array $expected The po result array
     * @return void
     * @dataProvider parseFileProvider
     * @covers ::parseFile()
     */
    public function testParseFile(string $file, string $extension, array $expected): void
    {
        $method = self::getMethod('parseFile');
        $method->invokeArgs($this->shell, [$file, $extension]);
        $actual = $this->shell->getPoResult();
        $this->recursiveSort($expected);
        $this->recursiveSort($actual);
        static::assertEquals($expected, $actual);
    }

    /**
     * Recursive ksort/sort arrays used in tests
     *
     * @param array $array Array to sort
     * @return void
     */
    protected function recursiveSort(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveSort($value);
            }
        }
        if (array_values($array) === $array) {
            sort($array);

            return;
        }

        ksort($array);
    }

    /**
     * Provider for 'testParseDir'
     *
     * @return array
     */
    public function parseDirProvider(): array
    {
        return [
            'contents dir' => [
                sprintf('%s/tests/files/gettext/contents', getcwd()), // dir
                [
                    'default' => [
                        'This is a twig sample',
                        'A twig content',
                        'A twig string with \"double quotes\"',
                        "A twig string with 'single quotes'",
                        'This is a php sample',
                        'A php content',
                        'A php string with \"double quotes\"',
                        'A php string with \'single quotes\'',
                        '1 test __',
                        '2 test __',
                        '3 test __',
                        '4 test __',
                        '1 test __n',
                        '1 test __x',
                        '1 test __xn',
                        '1 test __dx',
                        '1 test __dxn',
                    ],
                    'DomainSampleD' => [
                        'A twig string in a domain',
                        '1 test __d',
                    ],
                    'DomainSampleDN' => [
                        '1 test __dn',
                    ],
                ], // result
            ],
        ];
    }

    /**
     * Test 'parseDir,', 'parseFile' and 'parseContent' functions
     *
     * @param string $dir The directory containing files to parse
     * @param array $expected The po result array
     * @return void
     * @dataProvider parseDirProvider
     * @covers ::parseDir()
     * @covers ::parseFile()
     * @covers ::parseContent()
     * @covers ::parseContentSecondArg()
     * @covers ::parseContentThirdArg()
     * @covers ::strposX()
     * @covers ::fixString()
     */
    public function testParseDir(string $dir, array $expected): void
    {
        $method = self::getMethod('parseDir');
        $method->invokeArgs($this->shell, [$dir]);
        $actual = $this->shell->getPoResult();
        $this->recursiveSort($expected);
        $this->recursiveSort($actual);
        static::assertEquals($expected, $actual);
    }

    /**
     * Get GettextShell method by name, making it accessible
     *
     * @param string $name The method name
     * @return \ReflectionMethod
     */
    protected static function getMethod($name): \ReflectionMethod
    {
        $class = new \ReflectionClass('BEdita\I18n\Shell\GettextShell');
        $method = $class->getMethod($name);
        $method->setAccessible(true);

        return $method;
    }

    /**
     * Clean files for test.
     *
     * @return void
     */
    private function cleanFiles(): void
    {
        $files = [
            sprintf('%s/tests/test_app/TestApp/Locale/master.pot', getcwd()),
            sprintf('%s/tests/test_app/TestApp/Locale/en_US/default.po', getcwd()),
            sprintf('%s/tests/test_app/TestApp/Locale/it_IT/default.po', getcwd()),
        ];
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
