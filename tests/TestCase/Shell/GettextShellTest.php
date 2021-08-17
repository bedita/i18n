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
use Cake\Core\Configure;
use Cake\TestSuite\ConsoleIntegrationTestCase;

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
     * @var GettextShell
     */
    protected $shell = null;

    /**
     * {@inheritDoc}
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
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->cleanFiles();
        parent::tearDown();
    }

    /**
     * Test update and private methods called inside update
     *
     * @covers ::update()
     */
    public function testUpdate()
    {
        $this->shell->params['app'] = sprintf('%s/tests/files/gettext/app', getcwd());

        // set localePath using reflection class
        $localePath = sprintf('%s/tests/files/gettext/app/resources/locales', getcwd());
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
    }

    /**
     * Provider for 'setupPaths'
     *
     * @return array
     */
    public function setupPathsProvider()
    {
        $base = getcwd();

        return [
            'app' => [
                'tests/files/gettext/app', // app path
                null, // start path
                null, // plugin path
                [
                    sprintf('%s/tests/files/gettext/app/src', $base),
                    sprintf('%s/tests/files/gettext/app/config', $base),
                    sprintf('%s/tests/files/gettext/app/templates', $base),
                ], // template paths
                sprintf('%s/tests/files/gettext/app/resources/locales', $base), // locale path
            ],
            'plugin' => [
                null, // app path
                sprintf('%s/tests/files/gettext', $base), // start path
                'dummy', // plugin name
                [
                    sprintf('%s/tests/files/gettext/plugins/dummy/src', $base),
                    sprintf('%s/tests/files/gettext/plugins/dummy/config', $base),
                    sprintf('%s/tests/files/gettext/plugins/dummy/templates', $base),
                ], // template paths
                sprintf('%s/tests/files/gettext/plugins/dummy/resources/locales', $base), // locale path
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
     *
     * @dataProvider setupPathsProvider
     * @covers ::setupPaths()
     */
    public function testSetupPaths($appPath, $startPath, $pluginName, array $expectedTemplatePaths, string $expectedLocalePath)
    {
        $expectedPoName = 'default.po';
        if (!empty($appPath)) {
            $this->shell->params['app'] = sprintf('%s/%s', getcwd(), $appPath);
        }
        if (!empty($startPath)) {
            $this->shell->params['startPath'] = $startPath;
        }
        if (!empty($pluginName)) {
            $this->shell->params['plugin'] = $pluginName;
            $expectedPoName = sprintf('%s.po', $pluginName);
        }
        $method = self::getMethod('setupPaths');
        $method->invokeArgs($this->shell, []);
        static::assertEquals($expectedTemplatePaths, $this->shell->templatePaths);
        static::assertEquals($expectedLocalePath, $this->shell->localePath);
        static::assertEquals($expectedPoName, $this->shell->poName);
    }

    /**
     * Test writeMasterPot
     *
     * @covers ::writeMasterPot()
     * @return void
     */
    public function testWriteMasterPot()
    {
        // set localePath using reflection class
        $localePath = sprintf('%s/tests/files/gettext/app/resources/locales', getcwd());
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
     *
     * @covers ::header()
     * @covers ::writePoFiles()
     * @covers ::analyzePoFile()
     */
    public function testWritePoFiles()
    {
        // set localePath using reflection class
        $localePath = sprintf('%s/tests/files/gettext/app/resources/locales', getcwd());
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
                sprintf('something%swith%snew%slines', "\n", "\n", "\n", "\n"), // input
                sprintf('something%swith%snew%slines', '\n', '\n', '\n', '\n'), // expected
            ],
        ];
    }

    /**
     * Test fixString
     *
     * @param string $input The input string
     * @param string $expected The expected output string
     * @return void
     *
     * @dataProvider fixStringProvider
     * @covers ::fixString()
     */
    public function testFixString($input, $expected)
    {
        $method = self::getMethod('fixString');
        $args = [ $input ];
        $result = $method->invokeArgs($this->shell, $args);
        static::assertEquals($expected, $result);
    }

    /**
     * Provider for 'testParseDir'
     *
     * @return array
     */
    public function parseDirProvider()
    {
        return [
            'contents dir' => [
                sprintf('%s/tests/files/gettext/contents', getcwd()), // dir
                [
                    'This is a php sample',
                    'A php content',
                    'A php string with \"double quotes\"',
                    "A php string with 'single quotes'",
                    'This is a twig sample',
                    'A twig content',
                    'A twig string with \"double quotes\"',
                    "A twig string with 'single quotes'",
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
     *
     * @dataProvider parseDirProvider
     * @covers ::parseDir()
     * @covers ::parseFile()
     * @covers ::parseContent()
     * @covers ::fixString()
     */
    public function testParseDir(string $dir, array $expected)
    {
        $method = self::getMethod('parseDir');
        $method->invokeArgs($this->shell, [ $dir ]);
        static::assertEquals($expected, $this->shell->poResult);
    }

    /**
     * Get GettextShell method by name, making it accessible
     *
     * @param string $name The method name
     * @return \ReflectionMethod
     */
    protected static function getMethod($name)
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
    private function cleanFiles()
    {
        $files = [
            sprintf('%s/tests/files/gettext/app/resources/locales/master.pot', getcwd()),
            sprintf('%s/tests/files/gettext/app/resources/locales/en_US/default.po', getcwd()),
            sprintf('%s/tests/files/gettext/app/resources/locales/it_IT/default.po', getcwd()),
        ];
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
